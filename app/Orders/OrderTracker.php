<?php

declare(strict_types=1);

namespace App\Orders;

use App\Rtdb\Contracts\RtdbClient;

/**
 * The live state a customer's order tracker polls (PLAN §4 *Customer site* —
 * order tracker).
 *
 * The tracker is a 5–10 s fetch, no websockets. Every polled endpoint serves
 * from a **coalesced cache entry whose TTL ≥ the client's poll interval**
 * (PLAN §4 *Quota discipline*, rule 1), so N open trackers cost one RTDB read
 * per interval and the entry is never older than the cadence the client already
 * accepts. The state is read from the scalar `orders/{id}` node only — never the
 * sibling proof image — and renders whatever the order node says, degrading
 * gracefully on a state it does not recognise.
 *
 * The tracker is bound to the session and `users/{uid}` (via `customerUid`), so
 * it reopens with zero typing; the order code is displayed, never entered.
 */
final readonly class OrderTracker
{
    /**
     * The poll cadence the tracker fetches on, and the coalesced cache TTL —
     * the entry is never older than this, so the client never sees a stale
     * state past the interval it already tolerates.
     */
    public const POLL_SECONDS = 5;

    public function __construct(private RtdbClient $client) {}

    /**
     * The tracker state for an order, or null when the order does not exist.
     */
    public function state(string $orderId): ?OrderTrackerState
    {
        $node = $this->client->cached(
            $this->cacheKey($orderId),
            "orders/{$orderId}",
            self::POLL_SECONDS,
        );

        return is_array($node) ? OrderTrackerState::fromNode($orderId, $node) : null;
    }

    /**
     * The cache key for an order's coalesced tracker read.
     */
    public function cacheKey(string $orderId): string
    {
        return "order-state:{$orderId}";
    }

    /**
     * Evict an order's coalesced entry so its own write propagates on the next
     * poll rather than waiting out the cadence.
     */
    public function forget(string $orderId): void
    {
        $this->client->forget($this->cacheKey($orderId));
    }
}
