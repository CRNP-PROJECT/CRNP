<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The diner's business timezone, read once so every wall-clock evaluation in
 * the system — opening hours, closures, the close sweep, pickups, reports —
 * agrees regardless of the server's UTC clock.
 *
 * The value lives in `config/settings.php` (`BUSINESS_TIMEZONE`, defaulting to
 * `Asia/Manila`); this is the one place the app reads and defaults it, so the
 * fallback cannot drift between callers. Blade views read the config value
 * directly for display formatting.
 */
final class BusinessTime
{
    private function __construct() {}

    public static function timezone(): string
    {
        $timezone = config('settings.timezone');

        return is_string($timezone) && $timezone !== '' ? $timezone : 'Asia/Manila';
    }
}
