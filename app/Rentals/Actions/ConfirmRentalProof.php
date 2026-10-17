<?php

declare(strict_types=1);

namespace App\Rentals\Actions;

use App\Cache\AvailabilityCache;
use App\Orders\HandledBy;
use App\Rentals\RentalBookings;
use App\Rentals\RentalStatus;
use App\Rentals\RentalTracker;
use App\Rtdb\Contracts\RtdbClient;

/**
 * **Confirm** a flagged rental proof at the counter (PLAN §4 *Rentals* —
 * booking; CONTEXT — *Rental booking*).
 *
 * A booking whose proof amount/ref mismatched lands a **flagged hold**
 * (`verifying`) — the same exception-only gate as an order. Confirm is the
 * counter's acceptance: the booking moves to `confirmed` (units reserved, still
 * on hand), the flag markers are cleared, and `verifiedAt` is stamped with the
 * approval instant. Stock is untouched — units only move at handover.
 *
 * The tap is **status-guarded** — only a `verifying` booking may be confirmed —
 * so a stale or duplicate tap is a friendly no-op. The availability cache is
 * evicted so the newly-reserved units reflect in the catalog on the next read.
 */
final readonly class ConfirmRentalProof
{
    public function __construct(
        private RtdbClient $client,
        private RentalBookings $bookings,
        private AvailabilityCache $cache,
        private RentalTracker $tracker,
    ) {}

    public function __invoke(string $bookingId, HandledBy $handledBy): void
    {
        $booking = $this->bookings->find($bookingId);

        if ($booking === null || $booking->status !== RentalStatus::Verifying) {
            return;
        }

        $this->client->update("rentalBookings/{$bookingId}", [
            'status' => RentalStatus::Confirmed->value,
            'verifiedAt' => now('UTC')->toIso8601ZuluString(),
            'enteredAmount' => null,
            'flaggedAt' => null,
            'handledBy' => $handledBy->toNode(),
        ]);

        $this->cache->forgetRentals();
        $this->tracker->forget($bookingId);
    }
}
