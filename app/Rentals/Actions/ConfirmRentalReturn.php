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
 * Confirms a rented unit came back (CONTEXT — *Return*; PLAN §4 *Rentals* —
 * counter ops).
 *
 * This is the flow-level path **Add stock never had**: the units rise back onto
 * the shelf by the booking's quantity, the booking moves to `returned`, and the
 * cashier is stamped `handledBy`. Add stock stays for purchases. The tap is
 * **status-guarded** — only a **handed-over** booking may be returned, so a
 * stale or duplicate tap is a friendly no-op and stock can never be
 * double-counted.
 *
 * The return is confirmed **by the due date** (the booking's `returnDueAt`); a
 * late return is still a return — the overdue row is a quiet signal, never an
 * auto-penalty in v1. The availability cache is evicted so the catalog reflects
 * the raised stock on the next request.
 */
final readonly class ConfirmRentalReturn
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

        if ($booking === null || $booking->status !== RentalStatus::HandedOver) {
            return;
        }

        $item = $this->client->get("rentals/{$booking->itemId}");

        if (! is_array($item)) {
            return;
        }

        $units = is_numeric($item['units'] ?? null) ? (int) $item['units'] : 0;

        $this->client->update("rentals/{$booking->itemId}", ['units' => $units + $booking->qty]);
        $returnedAt = now('UTC')->toIso8601ZuluString();
        $this->client->update("rentalBookings/{$bookingId}", [
            'status' => RentalStatus::Returned->value,
            'returnedAt' => $returnedAt,
            // The terminal instant the proof-cleanup sweep (#25) keys off to
            // expire the booking's screenshot 7 days on.
            'terminalAt' => $returnedAt,
            'handledBy' => $handledBy->toNode(),
        ]);

        $this->cache->forgetRentals();
        $this->tracker->forget($bookingId);
    }
}
