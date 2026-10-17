<?php

declare(strict_types=1);

namespace App\Orders\Actions;

use App\Cache\CashierQueueCache;
use App\Cache\KitchenBoardCache;
use App\Orders\HandledBy;
use App\Orders\LineItem;
use App\Orders\Orders;
use App\Orders\OrderStatus;
use App\Orders\OrderTracker;
use App\Orders\Payment;
use App\Rtdb\Contracts\RtdbClient;
use App\Stats\Actions\TopThreeRanking;

/**
 * Marks an online pickup order **served** — the one happy-path tap (PLAN §4
 * *Cashier POS*).
 *
 * It flips the order to `served` (so the customer's tracker reads **READY** the
 * same instant and the kitchen ticket clears) and writes the 1:1 `sales/{id}`
 * row with `handledBy` (uid + name). The sale is keyed by the order id, so a
 * second sale is structurally impossible.
 *
 * The tap is **status-guarded**: only a **verified, unserved** order (the
 * `cooking` state auto-verify and a flagged hold's Approve both reach) may be
 * served. A stale tap on an unpaid order, or a duplicate tap on an already
 * served one, is a friendly no-op — the current status is asserted before any
 * write, so a last-write-wins flip can never happen.
 *
 * The order's own coalesced tracker entry and the queue's coalesced entry are
 * evicted, so the cashier's own tap propagates on the next request rather than
 * after the TTL. `settledAt` is stamped with the served instant so the daily
 * proof-cleanup sweep (#25) expires the screenshot 7 days later.
 *
 * The kitchen board's coalesced entry (#28) is evicted too, so Mark served
 * clears the ticket off the board the same instant.
 *
 * The sale write also recomputes the **Top 3** ranking (#31) eagerly, so the
 * product-card chip reflects this sale on the next menu read.
 */
final readonly class MarkServed
{
    public function __construct(
        private RtdbClient $client,
        private Orders $orders,
        private OrderTracker $tracker,
        private CashierQueueCache $queue,
        private KitchenBoardCache $board,
        private TopThreeRanking $ranking,
    ) {}

    public function __invoke(string $orderId, HandledBy $handledBy): void
    {
        $order = $this->orders->require($orderId);

        // Status-guarded: only a verified, unserved order may be served. A
        // stale or duplicate tap never flips the order and writes no sale.
        if ($order->status !== OrderStatus::Cooking) {
            return;
        }

        $servedAt = now('UTC')->toIso8601ZuluString();

        $this->client->update("orders/{$orderId}", [
            'status' => OrderStatus::Served->value,
            'servedAt' => $servedAt,
            'settledAt' => $servedAt,
            'handledBy' => $handledBy->toNode(),
        ]);

        // `sales/{id}` is 1:1 with the order key — the shared key makes a second
        // sale structurally impossible. The row is the same shape the close
        // sweep (#25) synthesizes, plus `handledBy`: an online order records its
        // tender as #23/#24 left it on the order node. `lines` ride along so the
        // Top 3 ranking (#31) can tally units per product without re-reading the
        // order.
        $this->client->set("sales/{$orderId}", [
            'code' => $order->code,
            'type' => $order->type,
            'lines' => array_map(static fn(LineItem $line): array => $line->toNode(), $order->lines),
            'total' => $order->total,
            'payments' => array_map(static fn(Payment $payment): array => $payment->toNode(), $order->payments),
            'soldAt' => $servedAt,
            'handledBy' => $handledBy->toNode(),
        ]);

        // Recompute Top 3 eagerly over the rolling window on this sale write.
        ($this->ranking)();

        $this->tracker->forget($orderId);
        $this->queue->forget();
        $this->board->forget();
    }
}
