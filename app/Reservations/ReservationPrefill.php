<?php

declare(strict_types=1);

namespace App\Reservations;

use App\Settings\ReservationArea;

/**
 * The values the guided entry carries from step 1 into step 2: the name, phone,
 * type, preferred type, and a starting party. It is what the phone typeahead
 * produces for a returning customer — the server fills name, party, and
 * preferred type from the past reservation so the cashier supplies only the
 * phone (PLAN §4 *Reservations*, ≤3 inputs).
 */
final readonly class ReservationPrefill
{
    public function __construct(
        public string $name,
        public string $phone,
        public ReservationArea $type,
        public ReservationArea $preferredType,
        public int $party,
        public bool $returning,
    ) {}

    /**
     * Resolve step 1 into step-2 values.
     *
     * A phone with a past reservation and no name typed is a **returning
     * customer**: the server prefills from the match. A supplied name (or no
     * match) is a **new customer**: the typed values stand, with a sensible
     * default party.
     */
    public static function forEntry(Typeahead $typeahead, string $phone, ?string $name, ?ReservationArea $type): self
    {
        $phone = trim($phone);
        $name = trim((string) $name);

        if ($name === '') {
            $match = $typeahead->search($phone)[0] ?? null;

            if ($match !== null) {
                return new self(
                    name: $match->name,
                    phone: $match->phone,
                    type: $type ?? $match->preferredType,
                    preferredType: $match->preferredType,
                    party: $match->party,
                    returning: true,
                );
            }
        }

        $resolved = $type ?? ReservationArea::DineIn;

        return new self(
            name: $name,
            phone: $phone,
            type: $resolved,
            preferredType: $resolved,
            party: 2,
            returning: false,
        );
    }

    public function draft(string $date, string $time, int $party): ReservationDraft
    {
        return new ReservationDraft(
            name: $this->name,
            phone: $this->phone,
            type: $this->type,
            preferredType: $this->preferredType,
            date: $date,
            time: $time,
            party: $party,
        );
    }
}
