<?php

declare(strict_types=1);

namespace App\Cache;

use App\Kitchen\KitchenBoard;
use Closure;
use Illuminate\Contracts\Cache\Repository;

/**
 * The `kitchen-board` cache entry — the coalesced read behind the kitchen
 * display (PLAN §4 *Quota discipline*, rule 1).
 *
 * The board is computed from a status-bounded query, not a single node read, so
 * this seam caches the **computed snapshot** under its own key rather than
 * routing through `RtdbClient::cached` (which reads one node). The TTL equals
 * the board's 5 s poll cadence, so N kiosks cost one computation per interval —
 * ten kiosks, one RTDB read — and the entry is never older than the cadence the
 * client already accepts.
 *
 * It is a distinct key from the catalog, availability, Top 3, and cashier-queue
 * entries, so a board read never forces a base64 catalog re-read. Mark served
 * evicts it, so a cashier's own tap drops the ticket on the next request rather
 * than after the TTL backstop.
 */
final readonly class KitchenBoardCache
{
    /**
     * The board entry — its own key, never the catalog.
     */
    public const KEY = 'kitchen-board';

    /**
     * The coalesced TTL: the poll cadence itself, so the entry is never staler
     * than the interval the client already tolerates.
     */
    public const TTL_SECONDS = KitchenBoard::POLL_SECONDS;

    public function __construct(private Repository $cache) {}

    /**
     * Serve the computed board from the cache, computing it once per cadence on
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
     * Evict the board entry so the next read recomputes.
     */
    public function forget(): void
    {
        $this->cache->forget(self::KEY);
    }
}
