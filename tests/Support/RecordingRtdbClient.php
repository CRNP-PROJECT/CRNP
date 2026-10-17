<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Rtdb\Contracts\RtdbClient;
use App\Rtdb\RtdbQuery;
use Illuminate\Support\Facades\Cache;

/**
 * A test double for {@see RtdbClient} that records every query it is asked to
 * run, so a test can assert a read stayed **bounded**.
 *
 * The quota rule is that no code path outside the export's small config trees
 * reads a whole node (PLAN §4 *Quota discipline*, rule 3), and the export
 * bootstraps in bounded pages. This double delegates every operation to the
 * in-memory client — a boundary double, not a mock of an internal collaborator —
 * while recording each `query()`'s path and `limitToFirst`, and each `get()`'s
 * path, so a test can observe the read shape.
 */
final class RecordingRtdbClient implements RtdbClient
{
    /**
     * Every query issued, in order.
     *
     * @var list<array{path: string, orderBy: string, limitToFirst: ?int, startAt: ?string}>
     */
    public array $queries = [];

    /**
     * Every `get()` path issued, in order.
     *
     * @var list<string>
     */
    public array $reads = [];

    public function __construct(private readonly RtdbClient $inner) {}

    public function get(string $path): mixed
    {
        $this->reads[] = $path;

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
        $this->queries[] = [
            'path' => $path,
            'orderBy' => $query->orderBy,
            'limitToFirst' => $query->limitToFirst,
            'startAt' => $query->startAt,
        ];

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

    /**
     * The largest `limitToFirst` used in a query against $path, or null when no
     * query touched that path.
     */
    public function maxPageSize(string $path): ?int
    {
        $max = null;

        foreach ($this->queries as $query) {
            if ($query['path'] !== $path || $query['limitToFirst'] === null) {
                continue;
            }

            $max = $max === null ? $query['limitToFirst'] : max($max, $query['limitToFirst']);
        }

        return $max;
    }
}
