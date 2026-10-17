<?php

declare(strict_types=1);

namespace App\Stats\Actions;

use App\Orders\HandledBy;
use App\Orders\Orders;
use App\Rtdb\Contracts\RtdbClient;

/**
 * **Mark refunded** — the admin-only action that settles a `refundOwed`
 * obligation (PLAN §4 *Admin* — *Reports*; CONTEXT — *Refund owed*).
 *
 * It clears the obligation on that order (writing `refundOwed` as null deletes
 * the RTDB key) and stamps `refundedAt` and the admin who pressed it, so the
 * Activity trail names the hand that settled the money. The order keeps its
 * status, its rejection record, and its sale — only the obligation goes.
 *
 * The action is **status-guarded**: it only settles an obligation that is still
 * outstanding, so a duplicate press is a friendly no-op.
 */
final readonly class MarkRefunded
{
    public function __construct(
        private RtdbClient $client,
        private Orders $orders,
    ) {}

    public function __invoke(string $orderId, HandledBy $refundedBy): void
    {
        $order = $this->orders->require($orderId);

        // Only an outstanding obligation is settled; a duplicate press writes
        // nothing.
        if ($order->refundOwed === null || $order->refundOwed <= 0) {
            return;
        }

        $this->client->update("orders/{$orderId}", [
            'refundOwed' => null,
            'refundedAt' => now('UTC')->toIso8601ZuluString(),
            'refundedBy' => $refundedBy->toNode(),
        ]);
    }
}
