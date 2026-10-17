<?php

declare(strict_types=1);

namespace App\Cache;

use App\Orders\CashierQueue;
use Closure;
use Illuminate\Contracts\Cache\Repository;

/**
 * The `cashier-queue` cache entry — the coalesced read behind the cashier's
 * online pickup queue (PLAN §4 *Quota discipline*, rule 1).
 *
 * The queue is computed from status-bounded queries, not a single node read, so
 * this seam caches the **computed snapshot** under its own key rather than
 * routing through `RtdbClient::cached` (which reads one node). The TTL equals
 * the screen's poll cadence, so N registers cost one computation per interval
 * and the entry is never older than the cadence the client already accepts.
 *
 * It is a distinct key from the catalog, availability, and Top 3 entries, so a
 * queue read never forces a base64 catalog re-read. Mark served evicts it, so
 * the cashier's own tap drops the row on the next request rather than after the
 * TTL backstop.
 */
final readonly class CashierQueueCache
{
    /**
     * The queue entry — its own key, never the catalog.
     */
    public const KEY = 'cashier-queue';

    /**
     * The coalesced TTL: the poll cadence itself, so the entry is never staler
     * than the interval the client already tolerates.
     */
    public const TTL_SECONDS = CashierQueue::POLL_SECONDS;

    public function __construct(private Repository $cache) {}

    /**
     * Serve the computed queue from the cache, computing it once per cadence on
     * a miss.
     *
     * @template T
     * @param  Closure(): T  $compute
     * @return T
     */
    public function remember(Closure $compute): mixed
    {
        /** @var T $value */
        $value = $this->cache->remember(self::KEY, self::TTL_SECONDS, $compute);

        return $value;
    }

    /**
     * Evict the queue entry so the next read recomputes.
     */
    public function forget(): void
    {
        $this->cache->forget(self::KEY);
    }
}
