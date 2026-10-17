<?php

declare(strict_types=1);

namespace App\Settings;

/**
 * Why the store is closed at an instant — the reason the open/closed badge
 * (#22) shows next to a disabled place-order button.
 */
enum ClosedReason: string
{
    case ForceClose = 'force_close';
    case ScheduledClosure = 'scheduled_closure';
    case OutsideHours = 'outside_hours';
    case NoHours = 'no_hours';
}
