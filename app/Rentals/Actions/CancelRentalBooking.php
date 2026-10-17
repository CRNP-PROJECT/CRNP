<?php

declare(strict_types=1);

namespace App\Rentals\Actions;

use App\Orders\HandledBy;
use App\Rentals\RentalBookings;
use App\Rentals\RentalStatus;
use App\Rentals\RentalTracker;
use App\Rtdb\Contracts\RtdbClient;

/**
 * **Cancel** a flagged rental booking at the counter (PLAN §4 *Rentals* —
 * booking).
 *
 * A flagged hold (`verifying`) whose proof the counter refuses ends here: the
 * booking moves to `cancelled` (terminal) and the exception is closed out
 * instead of being stuck forever. **Stock is untouched** — the units were never
 * handed over, so nothing moves (a rental booking only lowers stock at handover).
 *
 * The tap is **status-guarded** — only a `verifying` booking may be cancelled
 * from this surface — so a stale or duplicate tap is a friendly no-op.
 */
final readonly class CancelRentalBooking
{
    public function __construct(
        private RtdbClient $client,
        private RentalBookings $bookings,
        private RentalTracker $tracker,
    ) {}

    public function __invoke(string $bookingId, HandledBy $handledBy): void
    {
        $booking = $this->bookings->find($bookingId);

        if ($booking === null || $booking->status !== RentalStatus::Verifying) {
            return;
        }

        $this->client->update("rentalBookings/{$bookingId}", [
            'status' => RentalStatus::Cancelled->value,
            'cancelledAt' => now('UTC')->toIso8601ZuluString(),
            // The terminal instant the proof-cleanup sweep keys off.
            'terminalAt' => now('UTC')->toIso8601ZuluString(),
            'handledBy' => $handledBy->toNode(),
        ]);

        $this->tracker->forget($bookingId);
    }
}
