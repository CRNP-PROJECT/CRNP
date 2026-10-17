<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Orders\ExpirySweep;
use App\Orders\Jobs\ExpireOrdersJob;
use Illuminate\Console\Command;

/**
 * The **expiry** sweep, runnable by hand (PLAN §4 *Data layer* — *Expiry*).
 *
 * The scheduler runs this every minute in `Asia/Manila`; this command is the
 * **documented manual fallback** — run `php artisan orders:expire` when the
 * scheduler is down. It dispatches the unique {@see ExpireOrdersJob}, so a
 * manual run racing a scheduled tick cannot sweep the same orders twice.
 *
 * Dismisses only zero-proof, not-mid-upload orders past `deadlineAt` and clears
 * the cashier queue (the queue read excludes the `dismissed` status). The
 * scheduled read is bounded to {@see ExpirySweep::DEFAULT_LOOKBACK_HOURS}; after
 * a longer outage, widen it with `--lookback-hours` to reach older lapses.
 */
final class ExpireOrdersCommand extends Command
{
    protected $signature = 'orders:expire {--lookback-hours= : How far back to scan for lapsed deadlines}';

    protected $description = 'Dismiss expired, zero-proof, not-mid-upload online orders (expiry sweep).';

    public function handle(): int
    {
        $lookback = $this->option('lookback-hours');

        ExpireOrdersJob::dispatch($lookback !== null ? (int) $lookback : null);

        $this->components->info('Expiry sweep dispatched.');

        return self::SUCCESS;
    }
}
