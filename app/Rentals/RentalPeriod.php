<?php

declare(strict_types=1);

namespace App\Rentals;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * An inclusive date range a rental booking occupies (CONTEXT — *Rental
 * booking*).
 *
 * The stored shape is the plain `dateFrom` / `dateTo` pair (`Y-m-d`); this
 * object is the computed view the availability math and the total work from.
 * Two periods **overlap** when either start falls on or before the other's end,
 * so an overlap is detected without enumerating every date. A single-day rental
 * has `from === to` and covers exactly that date.
 */
final readonly class RentalPeriod
{
    /**
     * The longest range a customer may book, from config — the one place the
     * booking cap is read. It also bounds the availability overlap lookback (a
     * booking can never span more than this).
     */
    public static function maxDays(): int
    {
        $days = config('rentals.max_booking_days');

        return is_numeric($days) && (int) $days > 0 ? (int) $days : 60;
    }

    private function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
    ) {}

    public static function fromStrings(string $from, string $to): self
    {
        $start = self::parse($from);
        $end = self::parse($to);

        if ($end->lessThan($start)) {
            throw new InvalidArgumentException('The return date cannot be before the pickup date.');
        }

        return new self($start, $end);
    }

    public static function parse(string $date): CarbonImmutable
    {
        $parsed = CarbonImmutable::createFromFormat('!Y-m-d', trim($date));

        if ($parsed === null) {
            throw new InvalidArgumentException("The date [{$date}] is not a valid Y-m-d date.");
        }

        return $parsed->startOfDay();
    }

    /**
     * The number of whole days the range covers, inclusive of both ends — the
     * multiplier a per-day rate applies to.
     */
    public function days(): int
    {
        return (int) $this->from->diffInDays($this->to) + 1;
    }

    /**
     * Whether the range includes $date.
     */
    public function covers(CarbonInterface $date): bool
    {
        $day = CarbonImmutable::instance($date)->startOfDay();

        return ! $day->lessThan($this->from) && ! $day->greaterThan($this->to);
    }

    /**
     * Whether this range shares at least one date with another.
     */
    public function overlaps(self $other): bool
    {
        return ! $this->from->greaterThan($other->to) && ! $other->from->greaterThan($this->to);
    }

    public function fromString(): string
    {
        return $this->from->toDateString();
    }

    public function toString(): string
    {
        return $this->to->toDateString();
    }
}
