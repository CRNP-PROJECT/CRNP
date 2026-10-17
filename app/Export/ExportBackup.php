<?php

declare(strict_types=1);

namespace App\Export;

use App\Rtdb\Contracts\RtdbClient;
use App\Rtdb\RtdbQuery;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;

/**
 * The weekly **incremental export-backup** (PLAN §4 *Quota discipline*, rule 4).
 *
 * It reads every **durable** tree by `updatedAt > lastRun` — `orders`, `sales`,
 * `products`, `reservations`, and `rentalBookings` (when #34 has written any) —
 * and holds its **watermark and checkpoint** at `export/state`. The small config
 * trees (`staff`, `settings`, `rentals`, `stats`, `categories`) are read whole,
 * the one exception rule 3 allows.
 *
 * The **first run bootstraps in bounded pages of 500 rows**, checkpointing after
 * every page: never one whole-database read (which rule 3 bans and which, at
 * scale, would blow the monthly quota). Each durable tree keeps its own cursor,
 * so a tree that arrives later (rental bookings, #34) bootstraps independently
 * without re-reading the others. The partially-accumulated backup is written to
 * a **staging file** beside the checkpoint, so a run interrupted mid-bootstrap
 * resumes from where it stopped rather than re-reading or losing rows.
 *
 * **`proofs` is excluded** — a backup is a durable record, not an evidence
 * archive; the rejection record already carries entered-vs-total, ref#, who, and
 * when in text, and the images expire after 7 days regardless.
 *
 * A completed run writes one JSON file (the plan fixes no format, only the read
 * discipline) and advances `lastRun` to the newest `updatedAt` it saw, so the
 * next run reads only what changed. Rows are merged into the existing file, so
 * the backup accumulates rather than replacing history.
 */
final readonly class ExportBackup
{
    /** The durable-tree bootstrap page size. */
    public const PAGE_SIZE = 500;

    /** The staging file that accumulates a bootstrap across pages/runs. */
    public const STAGING_PATH = 'exports/backup-current.json';

    /**
     * The durable trees, each watermarked by `updatedAt`.
     *
     * @var list<string>
     */
    public const DURABLE_TREES = ['orders', 'sales', 'products', 'reservations', 'rentalBookings'];

    /**
     * The small config trees, read whole (rule 3's named exception).
     *
     * @var list<string>
     */
    public const CONFIG_TREES = ['staff', 'settings', 'rentals', 'stats', 'categories'];

    public function __construct(
        private RtdbClient $client,
        private Filesystem $disk,
    ) {}

    /**
     * Run one export pass and return the number of durable rows read.
     */
    public function __invoke(?CarbonImmutable $now = null): int
    {
        $at = $now ?? CarbonImmutable::instance(now('UTC'));
        $state = $this->state();
        $lastRun = is_string($state['lastRun'] ?? null) ? $state['lastRun'] : null;
        $checkpoint = $this->checkpoint($state);

        // A bootstrap in progress loads its partial backup from the staging file
        // so a resumed run continues rather than starting over; otherwise the
        // base is the last completed backup.
        /** @var array<string, array<string, mixed>> $backup */
        $backup = $checkpoint !== []
            ? $this->load(ExportBackup::STAGING_PATH)
            : $this->load(is_string($state['lastBackupPath'] ?? null) ? $state['lastBackupPath'] : null);

        $read = 0;
        $newest = $lastRun;

        foreach (self::DURABLE_TREES as $tree) {
            if ($lastRun === null || isset($checkpoint[$tree])) {
                $cursor = $checkpoint[$tree] ?? null;

                // Bootstrap: read bounded pages, persisting the partial backup
                // and the cursor after each page so an interruption resumes. Rows
                // merge by id, so re-reading a page boundary is idempotent.
                while (true) {
                    $before = count($backup[$tree] ?? []);
                    [$rows, $maxUpdatedAt, $done, $cursor] = $this->page($tree, $cursor);

                    $backup[$tree] = [...($backup[$tree] ?? []), ...$rows];
                    $read += count($rows);
                    $newest = self::newer($newest, $maxUpdatedAt);

                    $progressed = count($backup[$tree]) > $before;

                    if ($done || $cursor === null || ! $progressed) {
                        unset($checkpoint[$tree]);
                        break;
                    }

                    $checkpoint[$tree] = $cursor;
                    $this->persistBootstrap($backup, $checkpoint);
                }

                continue;
            }

            [$rows, $maxUpdatedAt] = $this->incremental($tree, $lastRun);
            $backup[$tree] = [...($backup[$tree] ?? []), ...$rows];
            $read += count($rows);
            $newest = self::newer($newest, $maxUpdatedAt);
        }

        foreach (self::CONFIG_TREES as $tree) {
            $node = $this->client->get($tree);

            if (is_array($node)) {
                $backup[$tree] = $node;
            }
        }

        $lastBackupPath = $this->path($at);
        $this->disk->put($lastBackupPath, $this->encode($backup));
        $this->disk->delete(self::STAGING_PATH);

        $newState = [
            'lastRun' => $newest,
            'lastBackupAt' => $at->toIso8601ZuluString(),
            'lastBackupPath' => $lastBackupPath,
        ];

        $this->client->set('export/state', $newState);

        return $read;
    }

    /**
     * Read one bounded page of a durable tree past $cursor, ordered by
     * `updatedAt`. Returns the rows, the newest `updatedAt` on the page, whether
     * the tree is exhausted, and the next cursor.
     *
     * @return array{array<string, array<string, mixed>>, ?string, bool, ?string}
     */
    private function page(string $tree, ?string $cursor): array
    {
        $query = RtdbQuery::orderBy('updatedAt')->limitToFirst(self::PAGE_SIZE + 1);

        if ($cursor !== null) {
            $query = $query->startAt($cursor);
        }

        $rows = $this->client->query($tree, $query);
        $done = count($rows) <= self::PAGE_SIZE;

        $page = [];
        $newest = null;

        foreach ($rows as $id => $row) {
            $updatedAt = is_string($row['updatedAt'] ?? null) ? $row['updatedAt'] : null;

            // RTDB's startAt is inclusive, so a resumed page re-reads the row the
            // checkpoint stopped on; skip it.
            if ($cursor !== null && $updatedAt !== null && $updatedAt <= $cursor) {
                continue;
            }

            $page[(string) $id] = $this->stringKeyedRow($row);

            if ($updatedAt !== null && ($newest === null || $updatedAt > $newest)) {
                $newest = $updatedAt;
            }

            if (count($page) === self::PAGE_SIZE) {
                break;
            }
        }

        $next = null;

        if (! $done && $page !== []) {
            $last = end($page);
            $next = is_string($last['updatedAt'] ?? null) ? $last['updatedAt'] : null;
        }

        return [$page, $newest, $done || $next === null, $next];
    }

    /**
     * Read every row in a durable tree at or past the watermark, returning the
     * rows and the newest `updatedAt` seen.
     *
     * RTDB has no exclusive lower bound, so the query reads from the watermark
     * inclusive. Rows at exactly the watermark are re-read (and merged by key
     * into the backup, so the re-read is idempotent) — which is what guarantees
     * the export **never skips an order**: a row written in the same second as
     * the previous run's watermark is still captured. The read stays bounded by
     * the watermark, so it costs only the rows since the last run.
     *
     * @return array{array<string, array<string, mixed>>, ?string}
     */
    private function incremental(string $tree, string $lastRun): array
    {
        $rows = $this->client->query($tree, RtdbQuery::orderBy('updatedAt')->startAt($lastRun));

        $fresh = [];
        $newest = null;

        foreach ($rows as $id => $row) {
            $updatedAt = is_string($row['updatedAt'] ?? null) ? $row['updatedAt'] : null;

            $fresh[(string) $id] = $this->stringKeyedRow($row);

            if ($updatedAt !== null && ($newest === null || $updatedAt > $newest)) {
                $newest = $updatedAt;
            }
        }

        return [$fresh, $newest];
    }

    /**
     * Persist the partial backup and the checkpoint mid-bootstrap, so a crash or
     * a manual stop resumes from here.
     *
     * @param  array<string, mixed>  $backup
     * @param  array<string, string>  $checkpoint
     */
    private function persistBootstrap(array $backup, array $checkpoint): void
    {
        $this->disk->put(self::STAGING_PATH, $this->encode($backup));
        $this->client->update('export/state', ['bootstrap' => $checkpoint]);
    }

    /**
     * @return array<string, mixed>
     */
    private function state(): array
    {
        $node = $this->client->get('export/state');

        return is_array($node) ? $this->stringKeyedRow($node) : [];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, string>
     */
    private function checkpoint(array $state): array
    {
        $bootstrap = $state['bootstrap'] ?? null;

        if (! is_array($bootstrap)) {
            return [];
        }

        $cursors = [];

        foreach ($bootstrap as $tree => $cursor) {
            if (is_string($tree) && is_string($cursor)) {
                $cursors[$tree] = $cursor;
            }
        }

        return $cursors;
    }

    /**
     * @return array<string, mixed>
     */
    private function load(?string $path): array
    {
        if ($path === null || $path === '' || ! $this->disk->exists($path)) {
            return [];
        }

        $decoded = json_decode((string) $this->disk->get($path), true);

        return is_array($decoded) ? $this->stringKeyedRow($decoded) : [];
    }

    /**
     * @param  array<string, mixed>  $backup
     */
    private function encode(array $backup): string
    {
        return (string) json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function path(CarbonImmutable $at): string
    {
        return 'exports/backup-' . $at->format('Ymd-His') . '.json';
    }

    private static function newer(?string $current, ?string $candidate): ?string
    {
        if ($candidate === null) {
            return $current;
        }

        if ($current === null || $candidate > $current) {
            return $candidate;
        }

        return $current;
    }

    /**
     * @param  array<mixed, mixed>  $row
     * @return array<string, mixed>
     */
    private function stringKeyedRow(array $row): array
    {
        $normalized = [];

        foreach ($row as $field => $value) {
            $normalized[(string) $field] = $value;
        }

        return $normalized;
    }
}
