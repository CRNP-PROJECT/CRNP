<?php

declare(strict_types=1);

namespace App\Reservations;

/**
 * A fixed time bucket a reservation's wall-clock time falls into.
 *
 * Reservations are grouped into slots of {@see DURATION_HOURS} hours anchored at
 * even hours, so 18:00, 18:15, 18:30, and 19:00 all share the `18:00` slot. The
 * slot is the unit the conflict check counts seats against and the public
 * occupancy view shows taken-vs-capacity for. It is computed, never stored, so
 * the stored shape stays the plain `{date, time}` the spec fixes.
 */
final readonly class TimeSlot
{
    /** The width of one slot in hours. */
    public const DURATION_HOURS = 2;

    private function __construct(
        public string $start,
        public string $end,
    ) {}

    /**
     * The slot a `HH:MM` wall-clock time belongs to.
     */
    public static function fromClock(string $time): self
    {
        $hour = (int) substr($time, 0, 2);
        $start = intdiv($hour, self::DURATION_HOURS) * self::DURATION_HOURS;

        return new self(
            sprintf('%02d:00', $start),
            sprintf('%02d:00', $start + self::DURATION_HOURS),
        );
    }

    public function label(): string
    {
        return "{$this->start}–{$this->end}";
    }

    /**
     * The stable key two reservations share when they are in the same slot.
     */
    public function key(): string
    {
        return $this->start;
    }
}
