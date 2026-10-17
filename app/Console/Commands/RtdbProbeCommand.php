<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Rtdb\Contracts\RtdbClient;
use Illuminate\Console\Command;
use Throwable;

/**
 * Writes and reads a node through RtdbClient to prove the console harness
 * drives the same seam the HTTP layer will, without a network.
 */
final class RtdbProbeCommand extends Command
{
    protected $signature = 'rtdb:probe {path=probe} {--value=ok}';

    protected $description = 'Write a value through RtdbClient and read it back.';

    public function handle(RtdbClient $client): int
    {
        $path = (string) $this->argument('path');
        $value = (string) $this->option('value');

        try {
            $client->set($path, ['value' => $value]);
            $read = $client->get($path);
        } catch (Throwable $exception) {
            $this->components->error("RTDB probe at [{$path}] failed: {$exception->getMessage()}");

            return self::FAILURE;
        }

        if (! is_array($read) || ($read['value'] ?? null) !== $value) {
            $this->components->error("RTDB probe at [{$path}] did not round-trip.");

            return self::FAILURE;
        }

        $this->components->info("RTDB probe round-tripped [{$path}].");

        return self::SUCCESS;
    }
}
