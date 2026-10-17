<?php

declare(strict_types=1);

namespace App\Reservations;

use App\Settings\ReservationArea;

/**
 * A returning customer the phone typeahead surfaces: the name, party, and
 * preferred type from their most recent past reservation. There is no parallel
 * customer table — the match is read from the reservation rows themselves
 * (PLAN §4 *Reservations*).
 */
final readonly class CustomerMatch
{
    public function __construct(
        public string $name,
        public string $phone,
        public int $party,
        public ReservationArea $preferredType,
    ) {}
}
