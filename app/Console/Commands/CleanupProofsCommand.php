<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Proofs\Jobs\CleanupProofsJob;
use App\Proofs\ProofCleanupSweep;
use Illuminate\Console\Command;

/**
 * The **proof cleanup** sweep, runnable by hand (PLAN §4 *Data layer* — proof
 * lifecycle).
 *
 * The scheduler runs this daily at end-of-day in `Asia/Manila`; this command is
 * the **documented manual fallback** — run `php artisan proofs:cleanup` when the
 * scheduler is down. It dispatches the unique {@see CleanupProofsJob}, so a
 * manual run racing a scheduled tick cannot race the same delete.
 *
 * Deletes a `proofs/{id}` node 7 days after a terminal order's `settledAt` or a
 * rejected proof's `rejectedAt`; the order, rejection record, and sale are left
 * untouched. The scheduled read is bounded to
 * {@see ProofCleanupSweep::DEFAULT_WINDOW_DAYS}; after a longer outage, widen it
 * with `--window-days`.
 */
final class CleanupProofsCommand extends Command
{
    protected $signature = 'proofs:cleanup {--window-days= : How wide an age window to scan for expired proofs}';

    protected $description = 'Delete proof images 7 days after settledAt or rejectedAt (proof cleanup sweep).';

    public function handle(): int
    {
        $window = $this->option('window-days');

        CleanupProofsJob::dispatch($window !== null ? (int) $window : null);

        $this->components->info('Proof cleanup sweep dispatched.');

        return self::SUCCESS;
    }
}
