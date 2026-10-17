<?php

declare(strict_types=1);

namespace App\Reservations;

use App\Settings\ReservationArea;

/**
 * A staff-entered reservation read from `reservations/{id}`.
 *
 * The shape is fixed by the spec: `{type, date, time, party, name, phone,
 * status, preferredType}` plus the `createdAt`/`updatedAt` watermarks. `date`
 * and `time` are the plain wall-clock strings the form supplies; the time slot
 * the conflict check and the occupancy view count in is computed, never stored.
 * `preferredType` is the area the customer last chose — the value the phone
 * typeahead prefills for a returning customer.
 */
final readonly class Reservation
{
    public function __construct(
        public string $id,
        public ReservationArea $type,
        public string $date,
        public string $time,
        public int $party,
        public string $name,
        public string $phone,
        public ReservationStatus $status,
        public ReservationArea $preferredType,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {}

    /**
     * @param  array<mixed, mixed>  $node
     */
    public static function fromNode(string $id, array $node): ?self
    {
        $type = is_string($node['type'] ?? null) ? ReservationArea::tryFrom($node['type']) : null;
        $status = is_string($node['status'] ?? null) ? ReservationStatus::tryFrom($node['status']) : null;
        $date = $node['date'] ?? null;
        $time = $node['time'] ?? null;
        $party = $node['party'] ?? null;
        $name = $node['name'] ?? null;
        $phone = $node['phone'] ?? null;

        if ($type === null || $status === null || ! is_string($date) || ! is_string($time)
            || ! is_numeric($party) || ! is_string($name) || ! is_string($phone)) {
            return null;
        }

        $preferred = is_string($node['preferredType'] ?? null)
            ? ReservationArea::tryFrom($node['preferredType'])
            : null;

        return new self(
            id: $id,
            type: $type,
            date: $date,
            time: $time,
            party: (int) $party,
            name: $name,
            phone: $phone,
            status: $status,
            preferredType: $preferred ?? $type,
            createdAt: is_string($node['createdAt'] ?? null) ? $node['createdAt'] : null,
            updatedAt: is_string($node['updatedAt'] ?? null) ? $node['updatedAt'] : null,
        );
    }

    public function slot(): TimeSlot
    {
        return TimeSlot::fromClock($this->time);
    }

    /**
     * Whether this reservation is in the same slot as another — the overlap the
     * conflict check and the duplicate rule key on.
     */
    public function overlaps(self $other): bool
    {
        return $this->date === $other->date && $this->slot()->key() === $other->slot()->key();
    }

    /**
     * Whether this reservation's phone and slot match another — the same caller
     * booking the same slot twice.
     */
    public function isDuplicateOf(self $other): bool
    {
        return $this->phone === $other->phone && $this->overlaps($other);
    }

    /**
     * Whether the reservation is live — pending or confirmed. A cancelled
     * reservation frees its seats and never blocks a new booking.
     */
    public function isActive(): bool
    {
        return $this->status !== ReservationStatus::Cancelled;
    }
}
