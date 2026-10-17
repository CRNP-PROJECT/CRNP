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
 * Hands a booked rental over at the counter (CONTEXT — *Handover*; PLAN §4
 * *Rentals* — counter ops).
 *
 * This is the tap that releases the units: **stock falls** by the booking's
 * quantity, the booking moves to `handed_over`, and the cashier is stamped
 * `handledBy`. The tap is **status-guarded** — only a **confirmed** booking may
 * be handed over, so a stale or duplicate tap is a friendly no-op and stock can
 * never be double-counted.
 *
 * The availability cache is evicted so the catalog reflects the lowered stock
 * on the next request. Rentals are their own stream: no order, no sale.
 */
final readonly class HandOverRental
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

        if ($booking === null || $booking->status !== RentalStatus::Confirmed) {
            return;
        }

        $item = $this->client->get("rentals/{$booking->itemId}");

        if (! is_array($item)) {
            return;
        }

        $units = is_numeric($item['units'] ?? null) ? (int) $item['units'] : 0;
        $remaining = max(0, $units - $booking->qty);

        $this->client->update("rentals/{$booking->itemId}", ['units' => $remaining]);
        $this->client->update("rentalBookings/{$bookingId}", [
            'status' => RentalStatus::HandedOver->value,
            'handedOverAt' => now('UTC')->toIso8601ZuluString(),
            'handledBy' => $handledBy->toNode(),
        ]);

        $this->cache->forgetRentals();
        $this->tracker->forget($bookingId);
    }
}
