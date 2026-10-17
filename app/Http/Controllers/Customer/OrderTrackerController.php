<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Auth\RtdbUser;
use App\Http\Controllers\Controller;
use App\Orders\Orders;
use App\Orders\OrderTracker;
use App\Orders\OrderTrackerState;
use App\Settings\SettingsRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The order tracker: a live status screen bound to the session and
 * `users/{uid}` (PLAN §4 *Customer site* — order tracker).
 *
 * The screen polls a small JSON state endpoint every {@see OrderTracker::POLL_SECONDS}
 * seconds (no websockets), served from a coalesced cache entry whose TTL equals
 * the cadence, so N trackers cost one RTDB read per interval. The order code is
 * displayed, never typed; the account's active order is reopenable with zero
 * typing. Only the order's owner (its `customerUid`) may read its tracker.
 */
final class OrderTrackerController extends Controller
{
    public function __construct(
        private readonly Orders $orders,
        private readonly OrderTracker $tracker,
        private readonly SettingsRepository $settings,
    ) {}

    /**
     * Reopen the account's active order with zero typing; with none, go to the
     * menu.
     */
    public function reopen(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof RtdbUser) {
            return redirect()->route('login');
        }

        $order = $this->orders->activeFor($user->sub);

        if ($order === null) {
            return redirect()->route('home');
        }

        return redirect()->route('orders.show', ['order' => $order->id]);
    }

    /**
     * The tracker screen for one order, owned by the signed-in account.
     */
    public function show(Request $request, string $order): View
    {
        $state = $this->ownedState($request, $order);

        $settings = $this->settings->current();

        return view('menu.tracker', [
            'state' => $state,
            'pollSeconds' => OrderTracker::POLL_SECONDS,
            'gcashNumber' => $settings->gcashNumber,
            'gcashQr' => $settings->gcashQr,
        ]);
    }

    /**
     * The live state the tracker polls — never older than the poll cadence.
     */
    public function state(Request $request, string $order): JsonResponse
    {
        return response()->json($this->ownedState($request, $order)->toArray());
    }

    private function ownedState(Request $request, string $orderId): OrderTrackerState
    {
        $user = $request->user();

        abort_unless($user instanceof RtdbUser, 403);

        // Read through the tracker's coalesced entry so a poll is one RTDB read.
        $state = $this->tracker->state($orderId);

        if ($state === null) {
            throw new NotFoundHttpException("No order with id [{$orderId}].");
        }

        abort_unless($state->customerUid === $user->sub, 403);

        return $state;
    }
}
