<?php

declare(strict_types=1);

namespace App\Reservations\Actions;

use App\Cache\AvailabilityCache;
use App\Rtdb\Contracts\RtdbClient;
use App\Reservations\Reservations;
use App\Reservations\ReservationStatus;

/**
 * Cancels a reservation, freeing its seats.
 *
 * Cancelling evicts the availability cache so the public occupancy view drops
 * the freed seats on the next request. A cancel of a reservation that is
 * already cancelled is a friendly no-op; a cancel of a confirmed one frees the
 * seats it held.
 */
final readonly class CancelReservation
{
    public function __construct(
        private RtdbClient $client,
        private Reservations $reservations,
        private AvailabilityCache $availability,
    ) {}

    public function __invoke(string $id): void
    {
        $reservation = $this->reservations->find($id);

        if ($reservation === null || $reservation->status === ReservationStatus::Cancelled) {
            return;
        }

        $this->client->update("reservations/{$id}", [
            'status' => ReservationStatus::Cancelled->value,
        ]);

        $this->availability->forget();
    }
}
