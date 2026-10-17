<?php

declare(strict_types=1);

namespace App\Export\Jobs;

use App\Export\ExportBackup;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs the **weekly export-backup** (PLAN §4 *Quota discipline*, rule 4).
 *
 * A queued job so the scheduler never blocks a request, and **unique** so two
 * overlapping ticks (or a manual run racing the scheduler) cannot write the
 * backup file and advance the watermark at once. The lock is bounded by
 * {@see uniqueFor()} so a crashed worker does not wedge the export forever.
 */
final class ExportBackupJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(ExportBackup $export): void
    {
        $export();
    }

    public function uniqueId(): string
    {
        return 'export:run';
    }

    public function uniqueFor(): int
    {
        return 3600;
    }
}
