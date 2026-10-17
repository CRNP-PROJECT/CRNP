<?php

declare(strict_types=1);

namespace App\Rtdb;

use App\Rtdb\Contracts\RtdbClient;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * The production `RtdbClient`: Firebase Realtime Database over the REST API,
 * authenticated with a cached service-account OAuth token.
 *
 * Reads are GET, writes are PUT (replace) or PATCH (merge) — never POST, since
 * the app owns its keys. A `{"error": ...}` body on a read is dropped as an
 * empty result so pages keep rendering; on a write it throws, because a
 * silently failed write corrupts the ledger.
 */
final class RestRtdbClient implements RtdbClient
{
    use StampsUpdatedAt;
    use ValidatesNodeKeys;

    public function __construct(
        private readonly string $baseUrl,
        private readonly ServiceAccountTokenProvider $tokens,
    ) {}

    #[\Override]
    public function get(string $path): mixed
    {
        $response = $this->request()->get($this->url($path));

        return $this->readBody($path, $response);
    }

    #[\Override]
    public function set(string $path, array $data): mixed
    {
        $response = $this->request()->put($this->url($path), $this->stampUpdatedAt($data));

        return $this->writeBody($path, $response);
    }

    #[\Override]
    public function update(string $path, array $data): mixed
    {
        $response = $this->request()->patch($this->url($path), $this->stampUpdatedAt($data));

        return $this->writeBody($path, $response);
    }

    #[\Override]
    public function delete(string $path): void
    {
        $response = $this->request()->delete($this->url($path));

        $this->writeBody($path, $response);
    }

    #[\Override]
    public function query(string $path, RtdbQuery $query): array
    {
        $response = $this->request()->get($this->url($path), $query->toQueryParams());

        $body = $this->readBody($path, $response);

        if (! is_array($body)) {
            return [];
        }

        $rows = [];

        foreach ($body as $key => $row) {
            if (! is_string($key) || ! is_array($row)) {
                continue;
            }

            $normalized = [];

            foreach ($row as $field => $value) {
                $normalized[(string) $field] = $value;
            }

            $rows[$key] = $normalized;
        }

        return $rows;
    }

    #[\Override]
    public function cached(string $key, string $path, int $ttlSeconds): mixed
    {
        return Cache::remember($key, $ttlSeconds, fn(): mixed => $this->get($path));
    }

    #[\Override]
    public function forget(string $key): void
    {
        Cache::forget($key);
    }

    private function request(): PendingRequest
    {
        return Http::withToken($this->tokens->token())
            ->acceptJson()
            ->asJson();
    }

    private function url(string $path): string
    {
        $this->assertValidPath($this->pathSegments($path));

        return rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/') . '.json';
    }

    /**
     * Decode a GET body, dropping a Firebase error object as an empty read.
     */
    private function readBody(string $path, Response $response): mixed
    {
        $data = $response->json();

        if (is_array($data) && array_key_exists('error', $data)) {
            Log::warning('RTDB read returned an error body.', [
                'path' => $path,
                'error' => is_string($data['error']) ? $data['error'] : json_encode($data['error']),
            ]);

            return [];
        }

        return $data;
    }

    /**
     * Decode a write body, throwing on any HTTP or Firebase error.
     */
    private function writeBody(string $path, Response $response): mixed
    {
        if (! $response->successful()) {
            throw new RuntimeException(
                "RTDB write to [{$path}] failed with HTTP {$response->status()}.",
            );
        }

        $data = $response->json();

        if (is_array($data) && array_key_exists('error', $data)) {
            $message = is_string($data['error']) ? $data['error'] : json_encode($data['error']);

            throw new RuntimeException("RTDB write to [{$path}] failed: {$message}");
        }

        return $data;
    }
}
