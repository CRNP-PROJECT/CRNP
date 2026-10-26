<?php

declare(strict_types=1);

namespace App\Orders;

/**
 * How a walk-in tender is paid. A walk-in carries cash, GCash, or both — the
 * cashier picks the method at settle and the server derives the legs
 * (PLAN §4 *Cashier POS*).
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
