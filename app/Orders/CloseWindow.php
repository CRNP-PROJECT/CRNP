<?php

declare(strict_types=1);

namespace App\Orders;

use App\Settings\Settings;
use App\Settings\SettingsRepository;
use App\Settings\Weekday;
use App\Support\BusinessTime;
use Carbon\CarbonInterface;

/**
 * Decides whether the **close** sweep is due at an instant (PLAN §4 *Data
 * layer* — *Close*).
 *
 * The diner closes at the weekday's closing hour from settings, and a
 * **force-close** override closes it immediately for an emergency. The
 * scheduler runs the close command every minute and this object is the filter
 * that fires it exactly on the closing minute — settings live in RTDB, so the
 * window is computed at run time, never baked into a cron string. All
 * wall-clock evaluation is in the business timezone, so the close sweep and the
 * customer's open/closed badge (#22) agree regardless of the server's UTC
 * clock.
 *
 * A force-close returns due every minute; the sweep itself is idempotent, so a
 * repeated run while the toggle is on writes nothing new.
 */
final readonly class CloseWindow
{
    public function __construct(private SettingsRepository $settings) {}

    public function isDueAt(CarbonInterface $when): bool
    {
        return $this->isDueFor($this->settings->current(), $when);
    }

    /**
     * Evaluate against an already-read settings value — the scheduler reads it
     * once per minute and passes it here.
     */
    public function isDueFor(Settings $settings, CarbonInterface $when): bool
    {
        if ($settings->forceClose) {
            return true;
        }

        $local = $when->copy()->setTimezone(BusinessTime::timezone());
        $hours = $settings->hoursFor(Weekday::fromDate($local));

        if ($hours === null) {
            return false;
        }

        return $local->format('H:i') === $hours->close;
    }
}
