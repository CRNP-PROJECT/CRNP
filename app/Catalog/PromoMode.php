<?php

declare(strict_types=1);

namespace App\Catalog;

/**
 * How an admin expressed a product promo.
 *
 * `Percent` means the admin typed a discount percent and the exact promo price
 * is the sibling value; `Amount` means the admin typed the exact promo price
 * and the discount percent is the sibling value. Either way the label is
 * computed, never typed (PLAN §4 *Item tags* — promo).
 */
enum PromoMode: string
{
    case Percent = 'percent';
    case Amount = 'amount';

    /**
     * The segmented control's unit: `%` for a discount percent, `₱` for an
     * exact price.
     */
    public function unit(): string
    {
        return match ($this) {
            self::Percent => '%',
            self::Amount => '₱',
        };
    }
}
