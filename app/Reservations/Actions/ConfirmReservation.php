<?php

declare(strict_types=1);

namespace App\Reservations\Actions;

use App\Cache\AvailabilityCache;
use App\Rtdb\Contracts\RtdbClient;
use App\Reservations\ReservationBlocked;
use App\Reservations\ReservationDraft;
use App\Reservations\ReservationGuard;
use App\Reservations\Reservations;
use App\Reservations\ReservationStatus;

/**
 * Confirms a pending reservation.
 *
 * Confirm **re-checks the conflict server-side** against fresh data before it
 * flips the status (PLAN §4 *Quota discipline*, rule 2: the public availability
 * cache may be stale, so the confirm is the last word). A blocked confirm
 * throws {@see ReservationBlocked} and leaves the reservation pending; a clean
 * one writes `confirmed` and evicts the availability cache. A confirm of a
 * reservation that is already confirmed or cancelled is a friendly no-op.
 */
final readonly class ConfirmReservation
{
    public function __construct(
        private RtdbClient $client,
        private Reservations $reservations,
        private ReservationGuard $guard,
        private AvailabilityCache $availability,
    ) {}

    public function __invoke(string $id): void
    {
        $reservation = $this->reservations->find($id);

        if ($reservation === null || $reservation->status !== ReservationStatus::Pending) {
            return;
        }

        $draft = new ReservationDraft(
            name: $reservation->name,
            phone: $reservation->phone,
            type: $reservation->type,
            preferredType: $reservation->preferredType,
            date: $reservation->date,
            time: $reservation->time,
            party: $reservation->party,
        );

        $conflict = $this->guard->check($draft, $id);

        if ($conflict->blocks()) {
            throw ReservationBlocked::withReason((string) $conflict->reason);
        }

        $this->client->update("reservations/{$id}", [
            'status' => ReservationStatus::Confirmed->value,
        ]);

        $this->availability->forget();
    }
}
