<?php

declare(strict_types=1);

namespace App\Rentals;

/**
 * The computed total of a rental booking: `pricePerDay × days × qty`, prepaid
 * in full with no deposit split (CONTEXT — *Rental booking*).
 *
 * The system does the math — the customer supplies a date range and a quantity,
 * and this is the one place the figure is derived, so the catalog, the booking
 * form, and the stored node can never disagree.
 */
final readonly class RentalTotal
{
    private function __construct() {}

    public static function for(RentalItem $item, RentalPeriod $period, int $qty): float
    {
        return round($item->pricePerDay * $period->days() * $qty, 2);
    }
}
