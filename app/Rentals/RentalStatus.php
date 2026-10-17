<?php

declare(strict_types=1);

namespace App\Rentals;

/**
 * A rental booking's lifecycle status (CONTEXT — *Rental booking*).
 *
 * A booking is created **awaiting payment** (the 15-minute GCash window runs);
 * a matching proof auto-verifies it to **confirmed** — the units are reserved
 * but still on hand. A proof whose entered amount or ref# mismatches lands a
 * **flagged hold** (`verifying`) for the counter's one-tap Confirm / Cancel,
 * the same exception-only gate as an order; an unresolved hold **lapses** at its
 * deadline with stock untouched. The counter's **Hand over** releases the units
 * (stock falls) and moves it to **handed over**; **Confirm return** brings them
 * back (stock rises) and moves it to **returned**. An unpaid booking **lapses**
 * with stock untouched — units were never handed over — and a booking the
 * customer abandons (or a flagged hold the counter refuses) can be
 * **cancelled**. All of returned / lapsed / cancelled are terminal.
 */
enum RentalStatus: string
{
    case AwaitingPayment = 'awaiting_payment';
    case Verifying = 'verifying';
    case Confirmed = 'confirmed';
    case HandedOver = 'handed_over';
    case Returned = 'returned';
    case Lapsed = 'lapsed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::AwaitingPayment => 'Awaiting payment',
            self::Verifying => 'Payment needs checking — see the counter',
            self::Confirmed => 'Confirmed',
            self::HandedOver => 'Handed over',
            self::Returned => 'Returned',
            self::Lapsed => 'Lapsed',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Whether the booking still holds the customer's interest — awaiting
     * payment, a flagged hold awaiting the counter, confirmed, or out on hire.
     * A terminal status (returned, lapsed, cancelled) is not active.
     */
    public function isActive(): bool
    {
        return in_array($this, [self::AwaitingPayment, self::Verifying, self::Confirmed, self::HandedOver], true);
    }

    /**
     * Whether the booking's units are reserved — confirmed but not yet handed
     * over. Only these overlap-subtract from on-hand stock when availability is
     * computed: a handed-over booking has already physically left (stock was
     * lowered at handover) and a returned one has come back.
     */
    public function reservesStock(): bool
    {
        return $this === self::Confirmed;
    }
}
