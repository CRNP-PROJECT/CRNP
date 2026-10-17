<?php

declare(strict_types=1);

namespace App\Orders;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * The 15-minute payment window an online order has to be paid (CONTEXT —
 * *Payment window*).
 *
 * The window starts when the GCash QR goes out — at placement — so
 * `deadlineAt = placedAt + 15 min` is computed here, in one place, and the
 * tracker and the expiry sweep (#25) read the same instant. A Restore re-arms a
 * fresh window (#29), which also calls {@see deadlineFrom()}.
 */
final readonly class PaymentWindow
{
    /**
     * The length of the payment window, in minutes.
     */
    public const MINUTES = 15;

    private function __construct() {}

    /**
     * The deadline for a window that starts at $placedAt.
     */
    public static function deadlineFrom(DateTimeInterface $placedAt): CarbonImmutable
    {
        return CarbonImmutable::instance($placedAt)->addMinutes(self::MINUTES);
    }
}
