<?php

declare(strict_types=1);

namespace App\Orders\Jobs;

use App\Orders\CloseSweep;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs the **close** sweep (PLAN §4 *Data layer* — *Close*).
 *
 * A queued job so the scheduler never blocks a request, and **unique** so a
 * force-close re-run cannot race the closing-hour run — though the sweep is
 * idempotent, the lock keeps the two from interleaving on the same order. The
 * lock is bounded by {@see uniqueFor()} so a crashed worker does not wedge the
 * sweep forever.
 */
final class CloseOrdersJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(CloseSweep $sweep): void
    {
        $sweep();
    }

    public function uniqueId(): string
    {
        return 'orders:close';
    }

    public function uniqueFor(): int
    {
        return 300;
    }
}
