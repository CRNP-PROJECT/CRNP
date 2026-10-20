<?php

declare(strict_types=1);

namespace App\Orders;

/**
 * How an order is served — **dine-in** or **take-out**.
 *
 * A dine-in is eaten on the premises (an onsite customer, often at a table); a
 * take-out is carried away. The choice rides on the order and is snapshotted
 * onto its `sales/{id}` row at settle, so the sales report can break a day's
 * revenue into the two without re-reading the orders. A backfilled historical
 * sale records it too, so live and encoded rows read the same.
 */
enum ServiceType: string
{
    case DineIn = 'dine_in';
    case TakeOut = 'take_out';

    public function label(): string
    {
        return match ($this) {
            self::DineIn => 'Dine-in',
            self::TakeOut => 'Take-out',
        };
    }
}
