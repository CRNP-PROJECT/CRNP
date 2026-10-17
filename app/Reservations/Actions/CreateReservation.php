<?php

declare(strict_types=1);

namespace App\Reservations\Actions;

use App\Cache\AvailabilityCache;
use App\Rtdb\Contracts\RtdbClient;
use App\Reservations\ReservationBlocked;
use App\Reservations\ReservationDraft;
use App\Reservations\ReservationGuard;
use Illuminate\Support\Str;

/**
 * Stores a staff-entered reservation.
 *
 * The conflict and duplicate check runs server-side first (PLAN §4
 * *Reservations*); a blocked draft throws {@see ReservationBlocked} with a
 * reason and nothing is written. A clean draft is stored **pending** — confirm
 * is a separate, re-checked step — and the write evicts the availability cache
 * so the public occupancy view reflects the booking on the next request.
 */
final readonly class CreateReservation
{
    public function __construct(
        private RtdbClient $client,
        private ReservationGuard $guard,
        private AvailabilityCache $availability,
    ) {}

    public function __invoke(ReservationDraft $draft): string
    {
        $conflict = $this->guard->check($draft);

        if ($conflict->blocks()) {
            throw ReservationBlocked::withReason((string) $conflict->reason);
        }

        $id = (string) Str::ulid();

        $this->client->set("reservations/{$id}", [
            ...$draft->toNode(),
            'createdAt' => now('UTC')->toIso8601ZuluString(),
        ]);

        $this->availability->forget();

        return $id;
    }
}
