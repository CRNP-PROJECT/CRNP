<?php

declare(strict_types=1);

namespace App\Orders;

use App\Rtdb\Contracts\RtdbClient;
use App\Rtdb\RtdbQuery;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The **expiry** sweep (PLAN §4 *Data layer* — *Expiry*; CONTEXT — *Dismissed*).
 *
 * Runs every minute against the indexed `deadlineAt = placedAt + 15 min` and
 * dismisses **only** orders that are **zero-proof *and* not mid-upload** — an
 * upload sent with no terminal response holds the order across 15:00, so a
 * customer is never dismissed for a connection they could not see failing.
 * `Restore` (#29) re-arms a fresh window, so a re-dismiss means a second real
 * lapse, not a bug.
 *
 * Dismissal is a single status write: the cashier queue and the kitchen board
 * read the payment/cooking states, so flipping to `Dismissed` clears them with
 * no extra write (the queue itself is #26). The tracker's coalesced entry is
 * evicted so the customer's next poll flips in-session.
 *
 * **Quota discipline** (PLAN §4, rule 3): `deadlineAt` is unbounded-below, so
 * the range is floored rather than opened to the epoch — the every-minute read
 * stays bounded to recently-lapsed windows instead of growing with order
 * history. A live order's deadline is at most one interval old (a Restore
 * re-arms a fresh window), so the default lookback is a wide margin for normal
 * operation; the documented manual fallback ({@see \App\Console\Commands\ExpireOrdersCommand})
 * widens it to recover from a longer scheduler outage.
 */
final readonly class ExpirySweep
{
    /**
     * The default lookback for the `deadlineAt` range. Only bounds the read —
     * an order lapses within one interval of its deadline, and a Restore
     * re-arms a fresh one.
     */
    public const DEFAULT_LOOKBACK_HOURS = 24;

    public function __construct(
        private RtdbClient $client,
        private OrderTracker $tracker,
    ) {}

    /**
     * Dismiss every expired, zero-proof, not-mid-upload order.
     *
     * @param  int|null  $lookbackHours  widen the range to recover from a
     *                                   scheduler outage; defaults to a day
     * @return list<string> the dismissed order ids
     */
    public function __invoke(?CarbonInterface $now = null, ?int $lookbackHours = null): array
    {
        $at = CarbonImmutable::instance($now ?? now('UTC'));
        $timestamp = $at->toIso8601ZuluString();
        $hours = $lookbackHours !== null && $lookbackHours > 0 ? $lookbackHours : self::DEFAULT_LOOKBACK_HOURS;
        $floor = $at->subHours($hours)->toIso8601ZuluString();

        $rows = $this->client->query(
            'orders',
            RtdbQuery::orderBy('deadlineAt')->range($floor, $timestamp),
        );

        $dismissed = [];

        foreach ($rows as $id => $row) {
            $order = Order::fromNode((string) $id, $row);

            if ($order === null || ! $this->shouldDismiss($order)) {
                continue;
            }

            $this->client->update("orders/{$order->id}", [
                'status' => OrderStatus::Dismissed->value,
                'dismissedAt' => $timestamp,
            ]);

            $this->tracker->forget($order->id);

            $dismissed[] = $order->id;
        }

        return $dismissed;
    }

    /**
     * Zero proof **and** nothing in flight, still inside the payment window.
     */
    private function shouldDismiss(Order $order): bool
    {
        return $order->status === OrderStatus::AwaitingPayment
            && $order->proofReceivedAt === null
            && ! $order->isMidUpload();
    }
}
