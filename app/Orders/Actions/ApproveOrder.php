<?php

declare(strict_types=1);

namespace App\Orders\Actions;

use App\Cache\CashierQueueCache;
use App\Cache\KitchenBoardCache;
use App\Orders\Orders;
use App\Orders\OrderStatus;
use App\Orders\OrderTracker;
use App\Rtdb\Contracts\RtdbClient;

/**
 * **Approve** — the flagged-hold tap and the undo of a rejection (PLAN §4
 * *Cashier POS* — *Rejected*; CONTEXT — *Flagged hold*).
 *
 * Two holds offer Approve:
 *
 * - a **flagged hold** (`verifying`) — entered amount ≠ total or a blank ref#.
 *   Approve promotes it to `cooking` (the kitchen ticket appears), clears the
 *   flag markers (`enteredAmount` / `flaggedAt`), and stamps `verifiedAt` with
 *   the approval instant — the age origin the board reads for an ASAP pickup.
 * - a **rejected** proof — Approve is the undo. It reverses the rejection: the
 *   obligation and the rejection markers are cleared and the ticket **rejoins
 *   the board by age** — the original ready-for / created time is kept, so the
 *   age timer is **never restarted** and the NEW badge has no fresh instant to
 *   flash on. It rejoins as the verified/kitchen state (`cooking`).
 *
 * The tap is **status-guarded**: only a `verifying` hold or a `rejected` order
 * may be approved, so a stale or duplicate tap on a served, dismissed, or
 * already-cooking order is a friendly no-op. The order's coalesced tracker
 * entry, the queue's coalesced entry, and the kitchen board's coalesced entry
 * are evicted so the tap propagates on the next request rather than after the
 * TTL.
 */
final readonly class ApproveOrder
{
    public function __construct(
        private RtdbClient $client,
        private Orders $orders,
        private OrderTracker $tracker,
        private CashierQueueCache $queue,
        private KitchenBoardCache $board,
    ) {}

    public function __invoke(string $orderId): void
    {
        $order = $this->orders->require($orderId);

        // A flagged hold is approvable: the cashier accepts the entered amount /
        // missing ref# and the ticket reaches the kitchen. The flag markers are
        // cleared and the approval instant becomes the age origin (`verifiedAt`).
        if ($order->status === OrderStatus::Verifying) {
            $this->client->update("orders/{$orderId}", [
                'status' => OrderStatus::Cooking->value,
                'enteredAmount' => null,
                'flaggedAt' => null,
                'verifiedAt' => now('UTC')->toIso8601ZuluString(),
            ]);

            $this->tracker->forget($orderId);
            $this->queue->forget();
            $this->board->forget();

            return;
        }

        // Status-guarded: only a rejected order may be approved next. A stale
        // tap on a served, dismissed, or already-cooking order never flips it.
        if ($order->status !== OrderStatus::Rejected) {
            return;
        }

        // The undo clears the obligation and the rejection record. `rejectedAt`
        // and `refundOwed` are written as null (which deletes the RTDB key), and
        // no timestamp is touched — the ticket rejoins by its original age.
        $this->client->update("orders/{$orderId}", [
            'status' => OrderStatus::Cooking->value,
            'enteredAmount' => null,
            'refundOwed' => null,
            'rejectedAt' => null,
            'rejection' => null,
        ]);

        $this->tracker->forget($orderId);
        $this->queue->forget();
        $this->board->forget();
    }
}
