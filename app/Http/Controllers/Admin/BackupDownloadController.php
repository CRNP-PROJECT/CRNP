<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Rtdb\Contracts\RtdbClient;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Downloads the last export-backup (PLAN §4 *Admin* — the export writes it).
 *
 * The path is read from `export/state` — the one place the export writes its
 * watermark — so the response always points at the newest completed backup; with
 * no backup yet, the request 404s.
 */
final class BackupDownloadController extends Controller
{
    public function __construct(private readonly RtdbClient $client) {}

    public function __invoke(): StreamedResponse
    {
        $state = $this->client->get('export/state');
        $path = is_array($state) && is_string($state['lastBackupPath'] ?? null) ? $state['lastBackupPath'] : null;

        if ($path === null || $path === '' || ! Storage::disk('local')->exists($path)) {
            throw new NotFoundHttpException('No backup has been written yet.');
        }

        return Storage::disk('local')->download($path);
    }
}
