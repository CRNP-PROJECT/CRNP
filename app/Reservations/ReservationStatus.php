<?php

declare(strict_types=1);

namespace App\Reservations;

/**
 * A reservation's lifecycle status. A staff-entered reservation starts
 * **pending** and moves to **confirmed** on the conflict-checked confirm, or to
 * **cancelled** when the booking falls through. Only confirmed bookings count
 * toward capacity (the public occupancy view) and the conflict check.
 */
enum ReservationStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Confirmed => 'Confirmed',
            self::Cancelled => 'Cancelled',
        };
    }
}
