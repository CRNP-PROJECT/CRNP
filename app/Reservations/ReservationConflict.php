<?php

declare(strict_types=1);

namespace App\Reservations;

/**
 * The outcome of the server-run date-time conflict and duplicate check.
 *
 * A clean draft carries no reason; a blocked one carries a plain sentence the
 * entry screen shows verbatim, so a double-booking is refused with a reason
 * rather than silently accepted. Confirm re-runs the same check against fresh
 * data, because the public availability cache may be stale.
 */
final readonly class ReservationConflict
{
    private function __construct(public ?string $reason) {}

    public static function none(): self
    {
        return new self(null);
    }

    public static function blocked(string $reason): self
    {
        return new self($reason);
    }

    public function blocks(): bool
    {
        return $this->reason !== null;
    }
}
