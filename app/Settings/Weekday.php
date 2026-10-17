<?php

declare(strict_types=1);

namespace App\Settings;

use Carbon\CarbonInterface;

/**
 * A day of the trading week, keyed by the short label stored in settings.
 *
 * ISO-8601 day numbers (1 = Monday … 7 = Sunday) map to these cases, so the
 * stored `hours` node is stable and language-independent.
 */
enum Weekday: string
{
    case Mon = 'mon';
    case Tue = 'tue';
    case Wed = 'wed';
    case Thu = 'thu';
    case Fri = 'fri';
    case Sat = 'sat';
    case Sun = 'sun';

    public function label(): string
    {
        return match ($this) {
            self::Mon => 'Monday',
            self::Tue => 'Tuesday',
            self::Wed => 'Wednesday',
            self::Thu => 'Thursday',
            self::Fri => 'Friday',
            self::Sat => 'Saturday',
            self::Sun => 'Sunday',
        };
    }

    /**
     * The weekday of an instant, evaluated on that instant's own calendar
     * (the caller is responsible for converting to the business timezone).
     */
    public static function fromDate(CarbonInterface $at): self
    {
        return match ((int) $at->format('N')) {
            1 => self::Mon,
            2 => self::Tue,
            3 => self::Wed,
            4 => self::Thu,
            5 => self::Fri,
            6 => self::Sat,
            default => self::Sun,
        };
    }
}
