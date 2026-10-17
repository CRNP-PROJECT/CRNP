<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Export\Jobs\ExportBackupJob;
use Illuminate\Console\Command;

/**
 * The **weekly export-backup**, runnable by hand (PLAN §4 *Quota discipline*,
 * rule 4).
 *
 * The scheduler runs this weekly in `Asia/Manila`; this command is the
 * **documented manual fallback** — run `php artisan export:run` when the
 * scheduler is down (see `docs/agents/operations.md`). It dispatches the unique
 * {@see ExportBackupJob}, so a manual run racing a scheduled tick cannot write
 * the backup and advance the watermark at once.
 *
 * The export is incremental by `updatedAt` and its first run bootstraps in
 * bounded pages, checkpointed at `export/state`, so a manual run after a long
 * outage resumes rather than re-reading the database. **Proofs are excluded.**
 */
final class ExportBackupCommand extends Command
{
    protected $signature = 'export:run';

    protected $description = 'Write the incremental backup (watermarked by updatedAt; proofs excluded).';

    public function handle(): int
    {
        ExportBackupJob::dispatch();

        $this->components->info('Export-backup dispatched.');

        return self::SUCCESS;
    }
}
