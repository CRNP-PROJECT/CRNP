<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Rtdb\Contracts\RtdbClient;
use App\Rtdb\RtdbQuery;
use Illuminate\Support\Facades\Cache;

/**
 * A test double for {@see RtdbClient} that counts reads per path.
 *
 * The quota rule is that a page view must not re-read the heavy catalog node
 * (whose `products` rows carry base64 images), so a test needs to observe how
 * many `get('products')` calls a request caused. It delegates every operation
 * to the in-memory client, so it stays a boundary double rather than a mock of
 * an internal collaborator.
 *
 * `cached()` re-implements the coalesced read through this wrapper's own `get`
 * so a cache miss is counted — otherwise the delegate's read would be invisible
 * to the counter and the "no re-read per view" claim could never be asserted.
 */
final class CountingRtdbClient implements RtdbClient
{
    /**
     * @var array<string, int>
     */
    public array $reads = [];

    /**
     * @var array<string, int>
     */
    public array $queries = [];

    public function __construct(private readonly RtdbClient $inner) {}

    public function get(string $path): mixed
    {
        $this->reads[$path] = ($this->reads[$path] ?? 0) + 1;

        return $this->inner->get($path);
    }

    public function set(string $path, array $data): mixed
    {
        return $this->inner->set($path, $data);
    }

    public function update(string $path, array $data): mixed
    {
        return $this->inner->update($path, $data);
    }

    public function delete(string $path): void
    {
        $this->inner->delete($path);
    }

    public function query(string $path, RtdbQuery $query): array
    {
        $this->queries[$path] = ($this->queries[$path] ?? 0) + 1;

        return $this->inner->query($path, $query);
    }

    public function cached(string $key, string $path, int $ttlSeconds): mixed
    {
        return Cache::remember($key, $ttlSeconds, fn(): mixed => $this->get($path));
    }

    public function forget(string $key): void
    {
        $this->inner->forget($key);
    }
}
