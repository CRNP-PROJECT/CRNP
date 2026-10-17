<?php

declare(strict_types=1);

namespace App\Reservations;

use RuntimeException;

/**
 * Raised when the server-run conflict or duplicate check blocks a reservation.
 *
 * Carries the plain reason the entry screen shows verbatim. It is a domain
 * failure, not a programming error: the controller catches it and returns the
 * reason to the person, who adjusts the slot and tries again.
 */
final class ReservationBlocked extends RuntimeException
{
    public static function withReason(string $reason): self
    {
        return new self($reason);
    }
}
