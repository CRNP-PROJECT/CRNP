<?php

declare(strict_types=1);

namespace App\Orders\Actions;

use App\Cache\CashierQueueCache;
use App\Cache\KitchenBoardCache;
use App\Orders\HandledBy;
use App\Orders\Orders;
use App\Orders\OrderStatus;
use App\Orders\OrderTracker;
use App\Orders\Rejection;
use App\Rtdb\Contracts\RtdbClient;

/**
 * **Reject** a payment proof — the cashier's exception tap (PLAN §4 *Cashier
 * POS* — *Rejected*).
 *
 * It writes the **rejection record** (order id, entered vs total, ref#, who,
 * when), keeps the proof at `proofs/{id}` as evidence (the #25 cleanup expires
 * it 7 days after `rejectedAt`), and books **`refundOwed`** as the **entered
 * amount labelled "claimed"** — never a figure nobody verified; admin **Mark
 * refunded** (#32) settles it. The status flip to `rejected` is what drops the
 * ticket **off the kitchen board the same instant** — the board mirrors
 * verified, unserved only — and the tracker reads *"Payment needs checking —
 * see the counter"* with both numbers.
 *
 * The tap is **status-guarded**: only a flagged hold (`verifying`) or a
 * spot-checked verified order (`cooking`) may be rejected, so a stale or
 * duplicate tap on a served, dismissed, or already-rejected order is a friendly
 * no-op rather than a last-write-wins flip. The order's own coalesced tracker
 * entry, the queue's coalesced entry, and the kitchen board's coalesced entry
 * are evicted, so the cashier's tap propagates on the next request rather than
 * after the TTL — **Reject drops the ticket off the board the same instant**.
 */
final readonly class RejectOrder
{
    public function __construct(
        private RtdbClient $client,
        private Orders $orders,
        private OrderTracker $tracker,
        private CashierQueueCache $queue,
        private KitchenBoardCache $board,
    ) {}

    public function __invoke(string $orderId, HandledBy $handledBy): void
    {
        $order = $this->orders->require($orderId);

        // Status-guarded: only a proof still awaiting a decision may be
        // rejected. A stale or duplicate tap never flips the order.
        if (! in_array($order->status, [OrderStatus::Verifying, OrderStatus::Cooking], true)) {
            return;
        }

        $rejectedAt = now('UTC')->toIso8601ZuluString();

        // The image is evidence: keep the node and anchor the retention clock so
        // the #25 cleanup deletes it 7 days after this rejection.
        $proof = $this->client->get("proofs/{$orderId}");

        // The entered amount is the "claimed" figure — what the customer typed,
        // not what the GCash app decided is owed. A flagged hold carries it on
        // the order node, but a spot-checked **verified** order cleared it
        // (`enteredAmount = null`) and left the figure on the proof node, so the
        // proof's `amount` is the fallback before the total.
        $enteredAmount = $order->enteredAmount
            ?? self::proofAmount($proof)
            ?? round($order->total, 2);

        $rejection = new Rejection(
            orderId: $order->id,
            enteredAmount: $enteredAmount,
            orderTotal: round($order->total, 2),
            ref: $order->gcashRef,
            by: $handledBy,
            at: $rejectedAt,
        );

        $this->client->update("orders/{$orderId}", [
            'status' => OrderStatus::Rejected->value,
            'rejectedAt' => $rejectedAt,
            // The claimed amount rides on the order so the customer's tracker
            // shows it beside the total even when the proof had auto-verified.
            'enteredAmount' => $enteredAmount,
            'refundOwed' => $enteredAmount,
            'rejection' => $rejection->toNode(),
        ]);

        if (is_array($proof)) {
            $this->client->update("proofs/{$orderId}", ['rejectedAt' => $rejectedAt]);
        }

        $this->tracker->forget($orderId);
        $this->queue->forget();
        $this->board->forget();
    }

    /**
     * The entered amount stored on the proof node, or null when there is none.
     */
    private static function proofAmount(mixed $proof): ?float
    {
        if (! is_array($proof)) {
            return null;
        }

        $amount = $proof['amount'] ?? null;

        return is_numeric($amount) ? round((float) $amount, 2) : null;
    }
}
