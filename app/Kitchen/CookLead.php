<?php

declare(strict_types=1);

namespace App\Kitchen;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * The **cook lead** — the head start a scheduled pickup gets before its pickup
 * time (CONTEXT — *Cook lead*).
 *
 * Hardcoded for v1 (PLAN §4 *Kitchen board*): a scheduled pickup is promoted
 * into NOW at `pickup − cook lead` (or at verify, whichever is later), and an
 * ASAP/walk-in order's ready-for is its verify/settle instant plus the lead.
 * One definition shared by promotion, ready-for, and the board's expiry, so the
 * lane moves and the timer can never disagree.
 */
final readonly class CookLead
{
    /**
     * The lead, in minutes.
     */
    public const MINUTES = 15;

    private function __construct() {}

    /**
     * The instant the lead opens before a pickup — the scheduled promotion
     * floor.
     */
    public static function before(DateTimeInterface $pickup): CarbonImmutable
    {
        return CarbonImmutable::instance($pickup)->subMinutes(self::MINUTES);
    }

    /**
     * The ready-for instant for an ASAP/walk-in order: when it became cookable
     * plus the lead.
     */
    public static function after(DateTimeInterface $cookable): CarbonImmutable
    {
        return CarbonImmutable::instance($cookable)->addMinutes(self::MINUTES);
    }
}
