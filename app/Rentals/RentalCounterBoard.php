<?php

declare(strict_types=1);

namespace App\Rentals;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Computes the counter's rental task lists (PLAN §4 *Rentals* — counter ops).
 *
 * Reads the `confirmed` bookings (to hand over), the `handed_over` bookings (to
 * return), and splits the latter into **overdue** — past `returnDueAt` — and
 * **on hire**. It also reads the `verifying` flagged holds, so an amount/ref
 * mismatch reaches a human for a one-tap Confirm / Cancel. The overdue row is
 * quiet and carries no penalty; a late return is still confirmed by the same
 * tap. Each read is a status-bounded query, so the counter never scans the
 * booking tree.
 */
final readonly class RentalCounterBoard
{
    public function __construct(private RentalBookings $bookings) {}

    public function snapshot(?CarbonInterface $now = null): RentalCounter
    {
        $at = CarbonImmutable::instance($now ?? now('UTC'));

        $toReturn = [];
        $overdue = [];

        foreach ($this->bookings->byStatus(RentalStatus::HandedOver) as $booking) {
            if ($booking->isOverdueAt($at)) {
                $overdue[] = $booking;
            } else {
                $toReturn[] = $booking;
            }
        }

        $confirmed = array_values($this->bookings->byStatus(RentalStatus::Confirmed));
        $flagged = array_values($this->bookings->byStatus(RentalStatus::Verifying));

        usort($confirmed, static fn(RentalBooking $a, RentalBooking $b): int => self::due($a) <=> self::due($b));
        usort($flagged, static fn(RentalBooking $a, RentalBooking $b): int => self::due($a) <=> self::due($b));
        usort($overdue, static fn(RentalBooking $a, RentalBooking $b): int => self::due($a) <=> self::due($b));
        usort($toReturn, static fn(RentalBooking $a, RentalBooking $b): int => self::due($a) <=> self::due($b));

        return new RentalCounter($confirmed, $toReturn, $overdue, $flagged);
    }

    /**
     * The sort key for a booking's due date, with a missing one last.
     */
    private static function due(RentalBooking $booking): int
    {
        return $booking->returnDueAt !== null
            ? CarbonImmutable::parse($booking->returnDueAt)->getTimestamp()
            : PHP_INT_MAX;
    }
}
