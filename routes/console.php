<?php

declare(strict_types=1);

use App\Orders\CloseWindow;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled sweeps
|--------------------------------------------------------------------------
|
| The four sweeps from PLAN §4 *Data layer* and §4 *Rentals*, all evaluated in
| the diner's timezone (`Asia/Manila`; the server clock is UTC):
|
| - **Expiry** — every minute against `deadlineAt`, dismissing only zero-proof,
|   not-mid-upload orders.
| - **Close** — the tick runs every minute, but the {@see CloseWindow} filter
|   fires it only at the weekday closing hour (or while force-close is on). The
|   window lives in RTDB settings, so it is read at run time, never baked into
|   a cron string.
| - **Proof cleanup** — daily at end-of-day, deleting a proof 7 days after
|   `settledAt` / `rejectedAt`.
| - **Rental lapse** (#34) — every minute against the booking `deadlineAt`,
|   lapsing only zero-proof, not-mid-upload rentals with stock untouched.
|
| Each entry dispatches a unique job, and every sweep has a manual fallback:
| run `php artisan orders:expire`, `orders:close`, `proofs:cleanup`, or
| `rentals:lapse` by hand if the scheduler is down.
|
*/

Schedule::command('orders:expire')
    ->everyMinute()
    ->timezone('Asia/Manila')
    ->name('orders:expire');

Schedule::command('orders:close')
    ->everyMinute()
    ->timezone('Asia/Manila')
    ->when(fn(): bool => app(CloseWindow::class)->isDueAt(now('Asia/Manila')))
    ->name('orders:close');

Schedule::command('proofs:cleanup')
    ->dailyAt('23:59')
    ->timezone('Asia/Manila')
    ->name('proofs:cleanup');

Schedule::command('rentals:lapse')
    ->everyMinute()
    ->timezone('Asia/Manila')
    ->name('rentals:lapse');

/*
|--------------------------------------------------------------------------
| Weekly export-backup
|--------------------------------------------------------------------------
|
| The **incremental export-backup** (PLAN §4 *Quota discipline*, rule 4) reads
| every durable tree by `updatedAt > lastRun`, holding its watermark and
| checkpoint at `export/state`, and excludes `proofs`. Its manual fallback is
| `php artisan export:run` (docs/agents/operations.md).
|
*/

Schedule::command('export:run')
    ->weeklyOn(1, '03:00')
    ->timezone('Asia/Manila')
    ->name('export:run');
