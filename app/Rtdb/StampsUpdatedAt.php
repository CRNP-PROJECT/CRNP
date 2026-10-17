<?php

declare(strict_types=1);

namespace App\Rtdb;

/**
 * Stamps the `updatedAt` watermark every write must carry.
 *
 * RTDB has no native modification timestamp, and both the sweeps and the
 * incremental export read by this field.
 */
trait StampsUpdatedAt
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function stampUpdatedAt(array $data): array
    {
        return [...$data, 'updatedAt' => now('UTC')->toIso8601ZuluString()];
    }
}
