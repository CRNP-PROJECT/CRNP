<?php

declare(strict_types=1);

namespace App\Orders\Jobs;

use App\Orders\ExpirySweep;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs the **expiry** sweep (PLAN §4 *Data layer* — *Expiry*).
 *
 * A queued job so the scheduler never blocks a request, and **unique** so two
 * overlapping ticks (or a manual run racing the scheduler) cannot sweep the
 * same orders at once — a double dismissal would double-write `dismissedAt` and
 * double-evict the tracker. The lock is bounded by {@see uniqueFor()} so a
 * crashed worker does not wedge the sweep forever.
 *
 * The optional lookback widens the `deadlineAt` range for the documented manual
 * fallback, so an operator can recover lapses older than the scheduled window
 * after a scheduler outage.
 */
final class ExpireOrdersJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(private readonly ?int $lookbackHours = null) {}

    public function handle(ExpirySweep $sweep): void
    {
        $sweep(lookbackHours: $this->lookbackHours);
    }

    public function uniqueId(): string
    {
        return 'orders:expire';
    }

    public function uniqueFor(): int
    {
        return 300;
    }
}
