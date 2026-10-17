<?php

declare(strict_types=1);

namespace App\Cache;

use Closure;
use Illuminate\Contracts\Cache\Repository;

/**
 * The `availability` cache entry — the coalesced read behind the public
 * occupancy view and (later, #34) the rental per-date availability (PLAN §4
 * *Quota discipline*, rule 2).
 *
 * The occupancy is computed from a date-bounded query, not a single node read,
 * so this seam caches the **computed result** under its own key rather than
 * routing through `RtdbClient::cached` (which reads one node). It is still a
 * Laravel cache entry, so a burst of visitors costs one computation per TTL.
 *
 * It carries two distinct entries under two keys: the reservation **occupancy**
 * view ({@see self::KEY}) and the public **rental catalog** (#34,
 * {@see self::RENTALS_KEY}). Each is evicted by its own writes — reservation
 * and capacity writes for occupancy, rental booking and stock writes for the
 * catalog — so a booking propagates on the next request rather than after the
 * TTL backstop. It is safe to be briefly stale because confirm re-checks the
 * conflict server-side; the TTL only governs how long a customer may chase a
 * slot that filled. Both are distinct keys from the catalog and Top 3 entries —
 * an availability write never forces a base64 catalog re-read.
 */
final readonly class AvailabilityCache
{
    /** The occupancy/availability entry — its own key, never the catalog. */
    public const KEY = 'availability';

    /**
     * The public rental catalog entry (#34) — its own key beside the occupancy
     * entry, evicted by booking, stock, and item writes.
     */
    public const RENTALS_KEY = 'availability:rentals';

    /**
     * The TTL backstop for the nobody-edited case; writes are the propagation
     * path, so this only bounds reads no write has touched.
     */
    public const TTL_SECONDS = 60;

    public function __construct(private Repository $cache) {}

    /**
     * Serve the computed availability from the cache, computing it once per
     * TTL window on a miss.
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
     * Evict the availability entry so the next occupancy read recomputes.
     */
    public function forget(): void
    {
        $this->cache->forget(self::KEY);
    }

    /**
     * Serve the computed rental catalog from the cache, computing it once per
     * TTL window on a miss.
     *
     * @template T
     * @param  Closure(): T  $compute
     * @return T
     */
    public function rememberRentals(Closure $compute): mixed
    {
        /** @var T $value */
        $value = $this->cache->remember(self::RENTALS_KEY, self::TTL_SECONDS, $compute);

        return $value;
    }

    /**
     * Evict the rental catalog entry so the next catalog read recomputes.
     */
    public function forgetRentals(): void
    {
        $this->cache->forget(self::RENTALS_KEY);
    }
}
