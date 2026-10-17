<?php

declare(strict_types=1);

namespace App\Rentals;

use App\Rtdb\Contracts\RtdbClient;

/**
 * The live state a customer's rental tracker polls (PLAN §4 *Rentals*).
 *
 * Mirrors the order tracker: a 5–10 s fetch, no websockets, served from a
 * **coalesced cache entry whose TTL equals the poll interval** (PLAN §4 *Quota
 * discipline*, rule 1), so N open trackers cost one RTDB read per interval. The
 * state is read from the scalar `rentalBookings/{id}` node only — never a proof
 * image — and renders whatever the booking node says, degrading gracefully on a
 * status it does not recognise.
 *
 * The tracker is bound to the session and `users/{uid}` via `uid`, so it reopens
 * with zero typing; the booking code is displayed, never entered.
 */
final readonly class RentalTracker
{
    /**
     * The poll cadence the tracker fetches on, and the coalesced cache TTL.
     */
    public const POLL_SECONDS = 5;

    public function __construct(private RtdbClient $client) {}

    public function state(string $bookingId): ?RentalTrackerState
    {
        $node = $this->client->cached(
            $this->cacheKey($bookingId),
            "rentalBookings/{$bookingId}",
            self::POLL_SECONDS,
        );

        return is_array($node) ? RentalTrackerState::fromNode($bookingId, $node) : null;
    }

    public function cacheKey(string $bookingId): string
    {
        return "rental-state:{$bookingId}";
    }

    /**
     * Evict a booking's coalesced entry so its own write propagates on the next
     * poll rather than waiting out the cadence.
     */
    public function forget(string $bookingId): void
    {
        $this->client->forget($this->cacheKey($bookingId));
    }
}
