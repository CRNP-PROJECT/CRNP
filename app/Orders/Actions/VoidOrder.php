<?php

declare(strict_types=1);

namespace App\Orders\Actions;

use App\Cache\CashierQueueCache;
use App\Orders\HandledBy;
use App\Orders\Orders;
use App\Orders\OrderStatus;
use App\Orders\OrderTracker;
use App\Rtdb\Contracts\RtdbClient;

/**
 * **Void** an order — the terminal tap that ends a **Rejected** (#27) or
 * **Dismissed** (#29) order (PLAN §4 *Cashier POS* — *Rejected* / *Retired
 * orders*).
 *
 * Void flips the order to `voided` so it leaves every live read, and it **never
 * clears `refundOwed`** — a money obligation outlives the order and is settled
 * only by the admin's **Mark refunded** (#32). The same action serves both
 * retired lists: a Rejected order's only other tap is Approve (the undo), while
 * a Dismissed order's is Restore.
 *
 * The tap is **status-guarded**: only a `rejected` or `dismissed` order may be
 * voided, so a stale or duplicate tap on a served, cooking, or already-voided
 * order is a friendly no-op. When a `HandledBy` is supplied (the Dismissed path)
 * the terminal `settledAt` stamp — the proof-cleanup sweep's watermark — and the
 * cashier are written too; the Rejected path omits them. The order's coalesced
 * tracker entry and the queue's coalesced entry are evicted so the tap
 * propagates on the next request.
 */
final readonly class VoidOrder
{
    public function __construct(
        private RtdbClient $client,
        private Orders $orders,
        private OrderTracker $tracker,
        private CashierQueueCache $queue,
    ) {}

    public function __invoke(string $orderId, ?HandledBy $handledBy = null): void
    {
        $order = $this->orders->require($orderId);

        // Status-guarded: only a Rejected (#27) or Dismissed (#29) order is
        // voided here; any stale tap writes nothing.
        if ($order->status !== OrderStatus::Rejected && $order->status !== OrderStatus::Dismissed) {
            return;
        }

        // `refundOwed` is deliberately absent: a void ends the order but never
        // clears the obligation.
        $voidedAt = now('UTC')->toIso8601ZuluString();

        $write = [
            'status' => OrderStatus::Voided->value,
            'voidedAt' => $voidedAt,
        ];

        // The Dismissed path names the cashier and stamps the terminal
        // `settledAt` watermark; the Rejected path leaves both untouched.
        if ($handledBy !== null) {
            $write['settledAt'] = $voidedAt;
            $write['handledBy'] = $handledBy->toNode();
        }

        $this->client->update("orders/{$orderId}", $write);

        $this->tracker->forget($orderId);
        $this->queue->forget();
    }
}
