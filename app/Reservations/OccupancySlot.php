<?php

declare(strict_types=1);

namespace App\Reservations;

/**
 * One time slot of a day in the public occupancy view: the slot's label and the
 * per-area seat counts for confirmed bookings inside it.
 */
final readonly class OccupancySlot
{
    /**
     * @param  list<OccupancyCount>  $counts
     */
    public function __construct(
        public TimeSlot $slot,
        public array $counts,
    ) {}
}
