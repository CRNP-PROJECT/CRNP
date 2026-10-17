<?php

declare(strict_types=1);

namespace App\Settings;

use App\Support\BusinessTime;
use Carbon\CarbonInterface;

/**
 * Computes the open/closed state from settings: the weekday's hours, the
 * force-close override, and scheduled date-range closures.
 *
 * The customer site (#22) consumes this to render the open/closed badge and to
 * disable place-order while closed; rentals sit behind the same gate. All
 * wall-clock evaluation is in the business timezone, so the badge and the close
 * sweep agree regardless of the server's UTC clock.
 */
final readonly class StoreStatus
{
    public function __construct(private SettingsRepository $settings) {}

    public function isOpenAt(CarbonInterface $when): bool
    {
        return $this->stateAt($when)->isOpen;
    }

    public function stateAt(CarbonInterface $when): StoreState
    {
        return $this->stateFor($this->settings->current(), $when);
    }

    /**
     * Evaluate against an already-read settings value — the customer site reads
     * settings once per request and passes it here, avoiding a second read.
     */
    public function stateFor(Settings $settings, CarbonInterface $when): StoreState
    {
        $local = $when->copy()->setTimezone(BusinessTime::timezone());

        if ($settings->forceClose) {
            return StoreState::closed(ClosedReason::ForceClose);
        }

        $date = $local->toDateString();

        foreach ($settings->closures as $closure) {
            if ($closure->covers($date)) {
                return StoreState::closed(ClosedReason::ScheduledClosure, $closure->label);
            }
        }

        $hours = $settings->hoursFor(Weekday::fromDate($local));

        if ($hours === null) {
            return StoreState::closed(ClosedReason::NoHours);
        }

        return $hours->contains($local)
            ? StoreState::open()
            : StoreState::closed(ClosedReason::OutsideHours);
    }
}
