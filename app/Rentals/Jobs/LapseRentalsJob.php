<?php

declare(strict_types=1);

namespace App\Rentals\Jobs;

use App\Rentals\RentalLapseSweep;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs the **rental lapse** sweep (PLAN §4 *Rentals* — booking).
 *
 * A queued job so the scheduler never blocks a request, and **unique** so two
 * overlapping ticks cannot lapse the same booking at once. The lock is bounded
 * by {@see uniqueFor()} so a crashed worker does not wedge the sweep forever.
 *
 * The optional lookback widens the `deadlineAt` range for the documented manual
 * fallback, so an operator can recover lapses older than the scheduled window
 * after a scheduler outage.
 */
final class LapseRentalsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(private readonly ?int $lookbackHours = null) {}

    public function handle(RentalLapseSweep $sweep): void
    {
        $sweep(lookbackHours: $this->lookbackHours);
    }

    public function uniqueId(): string
    {
        return 'rentals:lapse';
    }

    public function uniqueFor(): int
    {
        return 300;
    }
}
