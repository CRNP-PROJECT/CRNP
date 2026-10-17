<?php

declare(strict_types=1);

namespace App\Orders;

/**
 * An order's lifecycle state (PLAN §4 *Data layer* — `orders/{id}` carries the
 * status).
 *
 * A walk-in is `Pending` from ring-up until it is settled: it is not yet on the
 * kitchen board, so no food is cooked for a walk-in who leaves the counter. At
 * settle it becomes `Cooking` — the kitchen ticket appears the same instant —
 * and the counter hands it over. `Served` is the terminal state the online
 * pickup queue reaches via Mark served (#26).
 *
 * An **online** order is `AwaitingPayment` from placement (the 15-minute
 * payment window). #24 moves it to `Verifying` / `Cooking` (auto-verify or a
 * flagged hold's Approve) and #26 to `Served`; the expiry sweep (#25) owns
 * `Dismissed` — the window lapsed with zero proof and nothing in flight — and
 * the close sweep (#25) owns `Unclaimed` — paid, cooked, never collected. #27
 * owns `Rejected` — its own state, reachable only by Approve (the undo) or
 * `Voided`; #29 adds Restore / Void / Collect late. `Voided` is the terminal
 * state a Rejected or Dismissed order reaches through Void — the order ends,
 * and any `refundOwed` survives it. #23 only creates `AwaitingPayment` and
 * renders whatever the order node says, degrading gracefully for the later
 * transitions.
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case AwaitingPayment = 'awaiting_payment';
    case Verifying = 'verifying';
    case Cooking = 'cooking';
    case Served = 'served';
    case Dismissed = 'dismissed';
    case Rejected = 'rejected';
    case Voided = 'voided';
    case Unclaimed = 'unclaimed';

    /**
     * Whether the order tracker treats this as the customer's active order.
     */
    public function isActive(): bool
    {
        return in_array($this, [self::AwaitingPayment, self::Verifying, self::Cooking], true);
    }

    /**
     * The tracker's customer-facing label. The later states carry their full
     * sentence so the tracker can render whatever the order node says. A flagged
     * hold (`verifying`) and a rejected proof both read the same sentence — the
     * customer self-corrects at the counter, no blame and no reason codes.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::AwaitingPayment => 'Awaiting payment',
            self::Verifying => 'Payment needs checking — see the counter',
            self::Cooking => 'Cooking',
            self::Served => 'READY',
            self::Dismissed => 'Order dismissed — no payment received',
            self::Rejected => 'Payment needs checking — see the counter',
            self::Voided => 'Order voided',
            self::Unclaimed => 'Unclaimed',
        };
    }
}
