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
 * Collects an **Unclaimed** order late — the customer turns up after close
 * (PLAN §4 *Cashier POS* — *Retired orders*).
 *
 * The order flips to `served` (the tracker reads READY) and stamps `handledBy`
 * on the action. It writes **no sale**: the close sweep (#25) already
 * synthesized the 1:1 `sales/{id}` row when it decided the order Unclaimed, and
 * the shared key makes a second write structurally impossible anyway — so this
 * tap deliberately never touches `sales/{id}`. The synthesized row is the sale;
 * Collect late only records the late hand-over.
 *
 * `settledAt` is stamped with the served instant (as *Mark served* does) so the
 * daily proof-cleanup sweep (#25) expires the screenshot 7 days later; the
 * synthesized sale's own `soldAt` is left untouched.
 *
 * The tap is **status-guarded**: only an `Unclaimed` order may be collected
 * late. A stale tap on any other state is a friendly no-op. The tracker's and
 * the queue's coalesced entries are evicted so the cashier's own tap propagates
 * on the next request.
 */
final readonly class CollectLate
{
    public function __construct(
        private RtdbClient $client,
        private Orders $orders,
        private OrderTracker $tracker,
        private CashierQueueCache $queue,
    ) {}

    public function __invoke(string $orderId, HandledBy $handledBy): void
    {
        $order = $this->orders->require($orderId);

        // Status-guarded: only an Unclaimed order may be collected late. A stale
        // tap on a served, cooking, or payment-state order writes nothing.
        if ($order->status !== OrderStatus::Unclaimed) {
            return;
        }

        // No `sales/{id}` write: the sale was synthesized at close, and the
        // shared key already makes a second one impossible.
        $servedAt = now('UTC')->toIso8601ZuluString();

        $this->client->update("orders/{$orderId}", [
            'status' => OrderStatus::Served->value,
            'servedAt' => $servedAt,
            'settledAt' => $servedAt,
            'handledBy' => $handledBy->toNode(),
        ]);

        $this->tracker->forget($orderId);
        $this->queue->forget();
    }
}
