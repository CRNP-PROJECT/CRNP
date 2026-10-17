<?php

declare(strict_types=1);

use App\Export\ExportBackup;
use App\Export\Jobs\ExportBackupJob;
use App\Rtdb\Contracts\RtdbClient;
use App\Staff\StaffRole;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Storage;
use Tests\Support\RecordingRtdbClient;

/**
 * #32 — the weekly incremental export-backup.
 *
 * The export runs on the Laravel scheduler (same mechanism as the sweeps, with
 * a documented manual fallback) and reads every durable tree by
 * `updatedAt > lastRun`, holding its watermark and checkpoint at `export/state`.
 * The first run **bootstraps in bounded pages of 500 rows**, checkpointed — never
 * one whole-database read. The small config trees (staff, settings, rentals,
 * stats, categories) are read whole. **Proofs are excluded** — a backup is a durable record,
 * not an evidence archive.
 */

beforeEach(function (): void {
    Storage::fake('local');

    $this->travelTo(new DateTimeImmutable('2026-10-06 12:00:00', new DateTimeZone('Asia/Manila')));

    seedSettings(['banner' => ['text' => 'Hello', 'visible' => true]]);
});

/**
 * Read the JSON backup the export wrote.
 *
 * @return array<string, mixed>
 */
function exportBackup(): array
{
    $path = rtdb()->get('export/state')['lastBackupPath'] ?? null;

    expect($path)->toBeString();

    $decoded = json_decode((string) Storage::disk('local')->get((string) $path), true);

    return is_array($decoded) ? $decoded : [];
}

it('exports the durable trees and the small config trees, and stamps export/state', function (): void {
    seedSale('s1', ['code' => 'AAA1', 'total' => 200.0, 'soldAt' => '2026-10-06T04:00:00Z', 'updatedAt' => '2026-10-06T04:00:00Z']);
    seedAdminOrder('o1', ['code' => 'ORD1', 'updatedAt' => '2026-10-06T04:00:00Z']);
    seedStaff('owner@example.com', StaffRole::Admin, 'Owner Santos');
    seedCategory('drinks', 'Drinks');

    $this->artisan('export:run')->assertSuccessful();

    $backup = exportBackup();

    expect($backup['sales'])->toHaveKey('s1')
        ->and($backup['orders'])->toHaveKey('o1')
        ->and($backup['staff'])->toBeArray()
        ->and($backup['settings'])->toBeArray()
        // A restore without categories would leave every product's categoryId
        // pointing at nothing and the menu without its headings.
        ->and($backup['categories'])->toHaveKey('drinks');

    $state = rtdb()->get('export/state');

    expect($state['lastRun'])->toBeString()
        ->and($state['lastBackupAt'])->toBeString()
        ->and($state['lastBackupPath'])->toBeString();
});

it('excludes proofs from the backup', function (): void {
    seedSale('s1', ['updatedAt' => '2026-10-06T04:00:00Z']);
    rtdb()->seed('proofs/s1', ['imageBase64' => 'data:image/jpeg;base64,SECRETPROOFBYTES']);

    $this->artisan('export:run')->assertSuccessful();

    $path = (string) rtdb()->get('export/state')['lastBackupPath'];
    $raw = (string) Storage::disk('local')->get($path);

    expect($raw)->not->toContain('SECRETPROOFBYTES')
        ->and(exportBackup())->not->toHaveKey('proofs');
});

it('runs incrementally by updatedAt, keeping the earlier rows', function (): void {
    seedSale('s1', ['code' => 'OLD1', 'total' => 100.0, 'updatedAt' => '2026-10-06T04:00:00Z']);

    $this->artisan('export:run')->assertSuccessful();
    $firstRun = rtdb()->get('export/state')['lastRun'];

    // A later sale, after the watermark.
    $this->travel(1)->hours();
    seedSale('s2', ['code' => 'NEW2', 'total' => 250.0, 'updatedAt' => now('UTC')->toIso8601ZuluString()]);

    $this->artisan('export:run')->assertSuccessful();

    $backup = exportBackup();

    expect($backup['sales'])->toHaveKey('s1')
        ->and($backup['sales'])->toHaveKey('s2')
        ->and(rtdb()->get('export/state')['lastRun'])->not->toBe($firstRun);
});

it('never skips a row written in the same second as the watermark', function (): void {
    seedSale('s1', ['code' => 'OLD1', 'total' => 100.0, 'updatedAt' => '2026-10-06T04:00:00Z']);

    $this->artisan('export:run')->assertSuccessful();

    // A second sale at the exact same second as the watermark — a boundary the
    // export must still capture (RTDB has no exclusive lower bound).
    seedSale('s2', ['code' => 'TIE2', 'total' => 250.0, 'updatedAt' => '2026-10-06T04:00:00Z']);

    $this->artisan('export:run')->assertSuccessful();

    expect(exportBackup()['sales'])->toHaveKey('s2');
});

it('bootstraps the first run in bounded pages of 500 rows', function (): void {
    for ($i = 0; $i < 600; $i++) {
        $key = str_pad((string) $i, 4, '0', STR_PAD_LEFT);
        seedSale("s{$key}", [
            'code' => "C{$key}",
            'total' => 10.0,
            'updatedAt' => sprintf('2026-10-06T04:%02d:%02dZ', intdiv($i, 60) % 60, $i % 60),
        ]);
    }

    $client = new RecordingRtdbClient(app(RtdbClient::class));
    app()->instance(RtdbClient::class, $client);

    $this->artisan('export:run')->assertSuccessful();

    // Every durable-tree read was bounded to one page plus the lookahead row;
    // the whole 600-row tree was never read in a single query.
    $maxPage = $client->maxPageSize('sales');

    expect($maxPage)->not->toBeNull()
        ->and($maxPage)->toBeLessThanOrEqual(ExportBackup::PAGE_SIZE + 1)
        ->and(exportBackup()['sales'])->toHaveCount(600);
});

it('resumes a mid-bootstrap run from its checkpoint', function (): void {
    for ($i = 0; $i < 600; $i++) {
        $key = str_pad((string) $i, 4, '0', STR_PAD_LEFT);
        seedSale("s{$key}", [
            'code' => "C{$key}",
            'total' => 10.0,
            'updatedAt' => sprintf('2026-10-06T04:%02d:%02dZ', intdiv($i, 60) % 60, $i % 60),
        ]);
    }

    // The state an interrupted first run leaves behind: the checkpoint at
    // `export/state` and the partial backup it had staged so far.
    rtdb()->seed('export/state', [
        'lastRun' => null,
        'bootstrap' => ['sales' => '2026-10-06T04:02:29Z'],
    ]);

    $staged = [];

    for ($i = 0; $i < 150; $i++) {
        $key = str_pad((string) $i, 4, '0', STR_PAD_LEFT);
        $staged["s{$key}"] = ['code' => "C{$key}", 'total' => 10.0];
    }

    Storage::disk('local')->put(
        ExportBackup::STAGING_PATH,
        (string) json_encode(['sales' => $staged]),
    );

    $this->artisan('export:run')->assertSuccessful();

    // The bootstrap finished from the checkpoint, with no duplicates.
    expect(exportBackup()['sales'])->toHaveCount(600)
        ->and(rtdb()->get('export/state')['bootstrap'] ?? null)->toBeNull();
});

it('dispatches the export as a unique job so a double-run cannot race', function (): void {
    expect(new ExportBackupJob())->toBeInstanceOf(ShouldBeUnique::class);
});

it('runs the weekly export on the Laravel scheduler', function (): void {
    $this->artisan('schedule:list')->assertSuccessful();

    $events = collect(app(Schedule::class)->events())
        ->keyBy(fn($event): string => (string) $event->description);

    expect($events->has('export:run'))->toBeTrue();

    $export = $events->get('export:run');

    expect($export->expression)->toBe('0 3 * * 1')
        ->and($export->timezone)->toBe('Asia/Manila');
});
