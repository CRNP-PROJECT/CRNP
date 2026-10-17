<?php

declare(strict_types=1);

namespace App\Proofs\Jobs;

use App\Proofs\ProofCleanupSweep;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs the **proof cleanup** sweep (PLAN §4 *Data layer* — proof lifecycle).
 *
 * A queued job so the scheduler never blocks a request, and **unique** so two
 * overlapping ticks cannot race the same delete. The lock is bounded by
 * {@see uniqueFor()} so a crashed worker does not wedge the sweep forever.
 *
 * The optional window widens the age range for the documented manual fallback,
 * so an operator can recover proofs older than the daily window after a
 * scheduler outage.
 */
final class CleanupProofsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(private readonly ?int $windowDays = null) {}

    public function handle(ProofCleanupSweep $sweep): void
    {
        $sweep(windowDays: $this->windowDays);
    }

    public function uniqueId(): string
    {
        return 'proofs:cleanup';
    }

    public function uniqueFor(): int
    {
        return 3600;
    }
}
