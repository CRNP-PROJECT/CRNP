<?php

declare(strict_types=1);

namespace App\Reservations;

/**
 * One day of the public occupancy view: the date and the slots that hold
 * confirmed bookings. A day with none still renders (with no slot rows), so the
 * week ahead is always fully visible.
 */
final readonly class OccupancyDay
{
    /**
     * @param  list<OccupancySlot>  $slots
     */
    public function __construct(
        public string $date,
        public array $slots,
    ) {}
}
