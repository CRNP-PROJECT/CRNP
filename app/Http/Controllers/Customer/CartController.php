<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Customer\AddOnPrefs;
use App\Customer\CartSession;
use App\Customer\OrderingGate;
use App\Customer\Storefront;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\UpdateCartRequest;
use App\Settings\SettingsRepository;
use App\Settings\StoreStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * The customer's cart: inline steppers and add-on picks with computed totals
 * (PLAN §4 *Customer site* — cart → checkout).
 *
 * Ordering requires a sign-in, so the route is behind `auth` and a signed-out
 * tap lands on the one Google button. The controller stores only the selection
 * (product id, quantity, add-on names) in the session; every total is rebuilt
 * from the current catalog, so the customer never supplies a price and the
 * figure they see matches the order the server will place.
 */
final class CartController extends Controller
{
    public function __construct(
        private readonly CartSession $session,
        private readonly Storefront $storefront,
        private readonly AddOnPrefs $addOnPrefs,
        private readonly SettingsRepository $settings,
        private readonly StoreStatus $status,
    ) {}

    public function show(): View
    {
        $settings = $this->settings->current();
        $cart = $this->session->cart($this->storefront);

        // Pre-check each line's add-ons from the customer's saved selection
        // (#22), so a returning customer does not re-pick what they already
        // chose. The cart's own picks win once they have been made.
        $savedAddOns = [];

        foreach ($cart->lines() as $line) {
            $savedAddOns[$line->productId] = $line->addOns === []
                ? $this->addOnPrefs->forProduct(auth()->user(), $line->productId)
                : array_map(static fn($addOn): string => $addOn->name, $line->addOns);
        }

        return view('menu.cart', [
            'cart' => $cart,
            'products' => $this->storefront->products(),
            'savedAddOns' => $savedAddOns,
            'gate' => OrderingGate::for($settings, $this->status, now()),
        ]);
    }

    public function update(UpdateCartRequest $request): RedirectResponse
    {
        $this->session->put(
            $request->productId(),
            $request->quantity(),
            $request->addOnNames(),
        );

        return back();
    }

    /**
     * Remove a product from the cart (the stepper's `−` past one).
     */
    public function destroy(string $product): RedirectResponse
    {
        $this->session->put($product, 0);

        return back();
    }
}
