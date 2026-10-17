<?php

declare(strict_types=1);

namespace App\Rentals;

/**
 * One item on the public rental catalog: the item plus its computed per-date
 * availability across the window (CONTEXT — *Rental stock*).
 *
 * The catalog renders price/day and this availability; the item's own units
 * and the reserved count per day both come from the availability math, so the
 * catalog never carries a hand-kept count.
 */
final readonly class RentalCatalogEntry
{
    /**
     * @param  list<RentalAvailabilityDay>  $availability
     */
    public function __construct(
        public RentalItem $item,
        public array $availability,
    ) {}
}
