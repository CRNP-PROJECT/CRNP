<?php

declare(strict_types=1);

namespace App\Orders;

/**
 * How a walk-in tender is paid. A walk-in carries cash, GCash, or both — the
 * cashier types one number (the cash handed over) and the split derives
 * (PLAN §4 *Cashier POS* — split tender).
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case Gcash = 'gcash';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Gcash => 'GCash',
        };
    }
}
