<?php

declare(strict_types=1);

namespace App\Rentals;

/**
 * One day of a rental item's per-date availability: the date, the on-hand
 * units, and the units reserved by confirmed bookings that overlap it, so the
 * catalog can show `available / units` for every date in the window.
 *
 * "Reserved" counts only bookings in the `confirmed` status — units are held
 * once paid, never while a booking is still awaiting payment (an unpaid booking
 * lapses with stock untouched). A handed-over booking has already left the
 * shelf (stock fell at handover) and a returned one has come back, so neither
 * subtracts here.
 */
final readonly class RentalAvailabilityDay
{
    public function __construct(
        public string $date,
        public int $units,
        public int $reserved,
    ) {}

    public function available(): int
    {
        return max(0, $this->units - $this->reserved);
    }

    public function isFull(): bool
    {
        return $this->available() <= 0;
    }

    public function seats(): string
    {
        return $this->available() . ' / ' . $this->units;
    }
}
