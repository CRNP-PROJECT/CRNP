<?php

declare(strict_types=1);

namespace App\Rentals;

use RuntimeException;

/**
 * Raised when a rental booking is refused — the requested range overlaps
 * confirmed bookings, or the item cannot cover the quantity.
 *
 * Carries the plain reason the booking form shows verbatim. It is a domain
 * failure, not a programming error: the controller catches it and returns the
 * reason to the person, who adjusts the range and tries again.
 */
final class RentalBlocked extends RuntimeException
{
    public static function withReason(string $reason): self
    {
        return new self($reason);
    }
}
