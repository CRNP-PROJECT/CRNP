<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Orders\Jobs\CloseOrdersJob;
use Illuminate\Console\Command;

/**
 * The **close** sweep, runnable by hand (PLAN §4 *Data layer* — *Close*).
 *
 * The scheduler runs this at the weekday closing hour (and on force-close) in
 * `Asia/Manila`; this command is the **documented manual fallback** — run
 * `php artisan orders:close` when the scheduler is down. It dispatches the
 * unique {@see CloseOrdersJob}, so a manual run racing a scheduled tick cannot
 * decide the same order twice.
 *
 * Decides **Unclaimed** for paid, cooked orders never collected, synthesizes the
 * 1:1 `sales/{id}` row once (idempotent), and clears the server-owned rows. A
 * **Rejected** order is left alone (#27's invariant).
 */
final class CloseOrdersCommand extends Command
{
    protected $signature = 'orders:close';

    protected $description = 'Decide Unclaimed, synthesize the sale, and clear server-owned rows (close sweep).';

    public function handle(): int
    {
        CloseOrdersJob::dispatch();

        $this->components->info('Close sweep dispatched.');

        return self::SUCCESS;
    }
}
