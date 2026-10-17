<?php

declare(strict_types=1);

namespace App\Orders\Actions;

use App\Cache\CashierQueueCache;
use App\Orders\AuditLog;
use App\Orders\HandledBy;
use App\Orders\Orders;
use App\Orders\OrderStatus;
use App\Orders\OrderTracker;
use App\Orders\PaymentWindow;
use App\Rtdb\Contracts\RtdbClient;

/**
 * Restores a **Dismissed** order — the explicit human act behind a re-armed
 * payment window (PLAN §4 *Cashier POS* — *Retired orders*).
 *
 * A Restore re-arms a **fresh 15-minute window** (`deadlineAt = now + 15 min`,
 * the same {@see PaymentWindow} the tracker and the expiry sweep share), flips
 * the order back to `awaiting_payment` so the customer's tracker flips back in
 * session, and writes an **audit entry** (order id, who, when). A re-dismiss
 * then means a second real lapse, not a bug. Unlimited restores, each an
 * explicit human act with its own entry.
 *
 * The tap is **status-guarded**: only a `Dismissed` order may be restored. A
 * stale tap on any other state — including a **Rejected** order, which is
 * never Restored — is a friendly no-op that writes no window and no audit entry.
 * The tracker's coalesced entry and the queue's coalesced entry are evicted, so
 * the cashier's own tap propagates on the next request.
 */
final readonly class Restore
{
    public function __construct(
        private RtdbClient $client,
        private Orders $orders,
        private OrderTracker $tracker,
        private CashierQueueCache $queue,
        private AuditLog $audit,
    ) {}

    public function __invoke(string $orderId, HandledBy $handledBy): void
    {
        $order = $this->orders->require($orderId);

        // Status-guarded: only a Dismissed order may be restored. A Rejected
        // order is never Restored, and any stale tap writes nothing.
        if ($order->status !== OrderStatus::Dismissed) {
            return;
        }

        $this->client->update("orders/{$orderId}", [
            'status' => OrderStatus::AwaitingPayment->value,
            'deadlineAt' => PaymentWindow::deadlineFrom(now('UTC'))->toIso8601ZuluString(),
            'restoredAt' => now('UTC')->toIso8601ZuluString(),
        ]);

        $this->audit->record($orderId, 'restore', $handledBy, $order->code);

        $this->tracker->forget($orderId);
        $this->queue->forget();
    }
}
