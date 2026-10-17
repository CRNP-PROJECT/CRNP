<?php

declare(strict_types=1);

namespace App\Reservations;

use App\Settings\SettingsRepository;

/**
 * The server-run date-time **conflict + duplicate check** (PLAN §4
 * *Reservations*). It runs before a reservation is stored and again before a
 * pending reservation is confirmed, because the public availability cache may
 * be stale and a slot can fill between entry and confirm.
 *
 * Two things block a booking:
 * - **Duplicate** — the same phone already holds a live reservation in the same
 *   slot on the same date (the caller booking the same table twice).
 * - **Conflict** — the area's live seats in that slot, plus the new party, would
 *   exceed the area's configured capacity.
 *
 * The check reads one date through the `date` index, so it never scans the tree.
 */
final readonly class ReservationGuard
{
    public function __construct(
        private Reservations $reservations,
        private SettingsRepository $settings,
    ) {}

    /**
     * Check a draft against the stored reservations, ignoring $exceptId (the
     * reservation being re-checked on confirm) so it does not block itself.
     */
    public function check(ReservationDraft $draft, ?string $exceptId = null): ReservationConflict
    {
        $candidate = $draft->asReservation($exceptId ?? 'draft', ReservationStatus::Pending);

        $others = array_filter(
            $this->reservations->forDate($draft->date),
            static fn(Reservation $reservation): bool => $reservation->isActive(),
        );

        foreach ($others as $id => $existing) {
            if ($id !== $exceptId && $candidate->isDuplicateOf($existing)) {
                return ReservationConflict::blocked(
                    'A reservation for this phone already exists in that time slot.',
                );
            }
        }

        $taken = 0;

        foreach ($others as $id => $existing) {
            if ($id !== $exceptId && $existing->type === $candidate->type && $existing->overlaps($candidate)) {
                $taken += $existing->party;
            }
        }

        $capacity = $this->settings->current()->capacityFor($draft->type);

        if ($taken + $draft->party > $capacity) {
            return ReservationConflict::blocked(
                "That slot has {$taken} of {$capacity} seats taken — {$draft->type->label()} is full.",
            );
        }

        return ReservationConflict::none();
    }
}
