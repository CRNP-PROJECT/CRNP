<?php

declare(strict_types=1);

namespace App\Reservations;

use App\Settings\ReservationArea;

/**
 * The values a reservation entry supplies, before the conflict check and the
 * write. The guided two-step screen produces one of these; the create action
 * turns it into a stored {@see Reservation}.
 */
final readonly class ReservationDraft
{
    public function __construct(
        public string $name,
        public string $phone,
        public ReservationArea $type,
        public ReservationArea $preferredType,
        public string $date,
        public string $time,
        public int $party,
    ) {}

    /**
     * The draft's own reservation-shaped view, so the conflict check can compare
     * it against stored rows without a stored id.
     */
    public function asReservation(string $id, ReservationStatus $status): Reservation
    {
        return new Reservation(
            id: $id,
            type: $this->type,
            date: $this->date,
            time: $this->time,
            party: $this->party,
            name: $this->name,
            phone: $this->phone,
            status: $status,
            preferredType: $this->preferredType,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toNode(): array
    {
        return [
            'type' => $this->type->value,
            'date' => $this->date,
            'time' => $this->time,
            'party' => $this->party,
            'name' => $this->name,
            'phone' => $this->phone,
            'status' => ReservationStatus::Pending->value,
            'preferredType' => $this->preferredType->value,
        ];
    }
}
