<?php

declare(strict_types=1);

namespace App\Reservations;

use App\Settings\ReservationArea;

/**
 * One area's aggregate for a slot in the public occupancy view: seats taken by
 * confirmed bookings vs the area's configured capacity. Aggregate only — it
 * carries no name, no phone, and nothing bookable.
 */
final readonly class OccupancyCount
{
    public function __construct(
        public ReservationArea $area,
        public int $taken,
        public int $capacity,
    ) {}

    public function seats(): string
    {
        return "{$this->taken} / {$this->capacity}";
    }

    public function isFull(): bool
    {
        return $this->taken >= $this->capacity;
    }
}
