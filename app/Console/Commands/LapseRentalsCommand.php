<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Rentals\Jobs\LapseRentalsJob;
use Illuminate\Console\Command;

/**
 * The **rental lapse** sweep, runnable by hand (PLAN §4 *Rentals*).
 *
 * The scheduler runs this every minute in `Asia/Manila`; this command is the
 * documented manual fallback — run `php artisan rentals:lapse` when the
 * scheduler is down. It dispatches the unique {@see LapseRentalsJob}, so a
 * manual run racing a scheduled tick cannot lapse the same booking twice.
 *
 * Lapses only zero-proof, not-mid-upload bookings past `deadlineAt`; stock is
 * untouched (units only move at handover).
 */
final class LapseRentalsCommand extends Command
{
    protected $signature = 'rentals:lapse {--lookback-hours= : How far back to scan for lapsed deadlines}';

    protected $description = 'Lapse unpaid rental bookings past their payment deadline (rental lapse sweep).';

    public function handle(): int
    {
        $lookback = $this->option('lookback-hours');

        LapseRentalsJob::dispatch($lookback !== null ? (int) $lookback : null);

        $this->components->info('Rental lapse sweep dispatched.');

        return self::SUCCESS;
    }
}
