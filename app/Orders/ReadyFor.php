<?php

declare(strict_types=1);

namespace App\Orders;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * When an order should be cooked and waiting — the **ready-for** instant
 * (CONTEXT — *Ready-for*; PLAN §4 *Kitchen board*).
 *
 * A scheduled pickup's ready-for is its pickup time; an ASAP online order's is
 * its verify instant + the cook lead; a walk-in's is its settle instant + the
 * cook lead. The lead is hardcoded for v1.
 *
 * The server owns the board, so it also owns the clear: a ticket that has sat
 * uncollected for {@see self::HANDOVER_GRACE_MINUTES} past its ready-for is
 * **cleared off the board** and lands in the counter's **ready — awaiting
 * handover** row (#29) — so nothing disappears without a trace. The close sweep
 * (#25) is the later decision that finally calls it **Unclaimed**.
 */
final readonly class ReadyFor
{
    /**
     * The cook lead before an ASAP online order or a walk-in is ready.
     */
    public const COOK_LEAD_MINUTES = 15;

    /**
     * How long a ready ticket may sit uncollected before the server clears it
     * off the board and into the counter's handover row.
     */
    public const HANDOVER_GRACE_MINUTES = 15;

    private function __construct() {}

    /**
     * The instant an order should be cooked and waiting, or null when it has no
     * anchor yet (an online order with no scheduled pickup and no verify).
     */
    public static function at(Order $order): ?CarbonImmutable
    {
        if ($order->pickupAt !== null) {
            return CarbonImmutable::parse($order->pickupAt);
        }

        $anchor = $order->verifiedAt ?? $order->settledAt;

        return $anchor !== null ? CarbonImmutable::parse($anchor)->addMinutes(self::COOK_LEAD_MINUTES) : null;
    }

    /**
     * The instant a ready ticket is cleared off the board and into the counter's
     * handover row.
     */
    public static function handoverAt(Order $order): ?CarbonImmutable
    {
        return self::at($order)?->addMinutes(self::HANDOVER_GRACE_MINUTES);
    }

    /**
     * Whether the server has cleared this ticket off the board into the counter's
     * ready — awaiting handover row: a paid, verified, unserved order past its
     * ready-for + grace.
     */
    public static function isAwaitingHandover(Order $order, DateTimeInterface $now): bool
    {
        if ($order->status !== OrderStatus::Cooking) {
            return false;
        }

        $handoverAt = self::handoverAt($order);

        return $handoverAt !== null && ! $handoverAt->greaterThan(CarbonImmutable::instance($now));
    }
}
