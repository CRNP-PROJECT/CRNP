<?php

declare(strict_types=1);

namespace App\Orders;

/**
 * How the cashier chooses to take payment at settle (PLAN §4 *Cashier POS*).
 *
 * `Cash` and `Gcash` are single-tender: the full total rides on one method.
 * `Split` carries both legs, with the cashier typing each leg's amount. The
 * chosen method decides which fields the settle form requires; the stored
 * `payments` legs still carry the authoritative {@see PaymentMethod}.
 */
enum TenderMethod: string
{
    case Cash = 'cash';
    case Gcash = 'gcash';
    case Split = 'split';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Gcash => 'GCash',
            self::Split => 'Cash + GCash',
        };
    }

    /**
     * Whether the cashier must type the cash handed over for this choice.
     */
    public function needsCash(): bool
    {
        return $this !== self::Gcash;
    }

    /**
     * Whether the cashier must type the GCash amount for this choice.
     */
    public function needsGcashAmount(): bool
    {
        return $this !== self::Cash;
    }

    /**
     * Whether this choice always carries a GCash leg that needs its ref#.
     */
    public function needsGcashRef(): bool
    {
        return $this->needsGcashAmount();
    }
}
