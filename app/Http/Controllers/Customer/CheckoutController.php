<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Auth\RtdbUser;
use App\Customer\CartSession;
use App\Customer\OrderingGate;
use App\Customer\Storefront;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\PlaceOrderRequest;
use App\Orders\Actions\PlaceOnlineOrder;
use App\Orders\PickupTime;
use App\Settings\SettingsRepository;
use App\Settings\StoreStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The checkout: pickup time (default ASAP, earliest now + 15 min) then Place
 * order (PLAN §4 *Customer site* — place order).
 *
 * The POST is validated, throttled (`throttle:checkout` — 10/min per signed-in
 * user, 30/min per real IP for anonymous), and carries a client-generated
 * idempotency key; a replay returns the original order code and opens no second
 * window. Ordering is gated on the store being open and the GCash payment block
 * existing — the same #22 {@see OrderingGate} the menu renders.
 *
 * The sign-in gate is enforced here rather than by the `auth` middleware so the
 * throttle runs first: behind `auth`, an anonymous request would redirect
 * before the limiter counted it, and the 30/min per-IP bucket could never fill
 * (PLAN §4 *Auth & roles*).
 */
final class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartSession $session,
        private readonly Storefront $storefront,
        private readonly SettingsRepository $settings,
        private readonly StoreStatus $status,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        if (! $request->user() instanceof RtdbUser) {
            return redirect()->route('login');
        }

        $settings = $this->settings->current();
        $earliest = PickupTime::earliest(now());

        return view('menu.checkout', [
            'cart' => $this->session->cart($this->storefront),
            'gate' => OrderingGate::for($settings, $this->status, now()),
            'earliestPickup' => $earliest->toIso8601String(),
            'minPickup' => PickupTime::minInput(now()),
            'idempotencyKey' => (string) \Illuminate\Support\Str::ulid(),
        ]);
    }

    public function store(
        PlaceOrderRequest $request,
        PlaceOnlineOrder $place,
    ): RedirectResponse {
        $user = $request->user();

        if (! $user instanceof RtdbUser) {
            return redirect()->route('login');
        }

        // Idempotency wins: a replayed key returns the ORIGINAL order and opens
        // no second window, even though the cart was spent on the first tap.
        $existing = $place->replay($request->idempotencyKey());

        if ($existing !== null) {
            return redirect()->route('orders.show', ['order' => $existing->id]);
        }

        $settings = $this->settings->current();

        // The gate is re-checked at POST: ordering is disabled while closed and
        // until the payment block exists, so a stale tab cannot place an order.
        abort_unless(
            OrderingGate::for($settings, $this->status, now())->canOrder,
            403,
        );

        $cart = $this->session->cart($this->storefront);

        if ($cart->isEmpty()) {
            return back()->withErrors(['cart' => 'Your cart is empty — add an item to order.']);
        }

        $placed = $place($user, $cart, $request->pickup(), $request->idempotencyKey());

        // The cart is spent once an order exists.
        $this->session->clear();

        // A fresh placement lands on the account's live tracker; a replay goes
        // straight to the ORIGINAL order, so the customer sees the same code and
        // no second 15-minute window is opened.
        return $placed->replayed
            ? redirect()->route('orders.show', ['order' => $placed->order->id])
            : redirect()->route('orders.tracker');
    }
}
