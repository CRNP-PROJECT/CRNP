<?php

declare(strict_types=1);

namespace App\Rtdb;

use App\Rtdb\Contracts\RtdbClient;
use Illuminate\Support\Facades\Cache;

/**
 * An in-memory `RtdbClient` for tests: the single substitution point that lets
 * the whole system be driven without network or credentials.
 *
 * It mirrors the REST client's contract — null for a missing node, `updatedAt`
 * stamped on every write, PATCH merging named children, PUT replacing the node,
 * and queries filtered on the indexed field.
 */
final class InMemoryRtdbClient implements RtdbClient
{
    use StampsUpdatedAt;
    use ValidatesNodeKeys;

    /**
     * The whole database, nested by path segment.
     *
     * @var array<string, mixed>
     */
    private array $store = [];

    #[\Override]
    public function get(string $path): mixed
    {
        return $this->read($this->segments($path));
    }

    #[\Override]
    public function set(string $path, array $data): mixed
    {
        $this->write($this->segments($path), $this->stampUpdatedAt($data));

        return $this->get($path);
    }

    #[\Override]
    public function update(string $path, array $data): mixed
    {
        $segments = $this->segments($path);
        $existing = $this->read($segments);
        $merged = is_array($existing) ? [...$existing, ...$this->stampUpdatedAt($data)] : $this->stampUpdatedAt($data);

        $this->write($segments, $merged);

        return $this->get($path);
    }

    #[\Override]
    public function delete(string $path): void
    {
        $segments = $this->segments($path);
        $key = array_pop($segments);

        if ($key === null) {
            $this->store = [];

            return;
        }

        $parent = &$this->reference($segments);

        if (is_array($parent)) {
            unset($parent[$key]);
        }
    }

    #[\Override]
    public function query(string $path, RtdbQuery $query): array
    {
        $node = $this->read($this->segments($path));

        if (! is_array($node)) {
            return [];
        }

        $rows = [];

        foreach ($node as $key => $row) {
            if (! is_string($key) || ! is_array($row) || ! array_key_exists($query->orderBy, $row)) {
                continue;
            }

            $raw = $row[$query->orderBy];

            if (! is_scalar($raw)) {
                continue;
            }

            $value = (string) $raw;

            if ($query->equalTo !== null && $value !== $query->equalTo) {
                continue;
            }

            if ($query->startAt !== null && $value < $query->startAt) {
                continue;
            }

            if ($query->endAt !== null && $value > $query->endAt) {
                continue;
            }

            $rows[$key] = $this->stringKeyed($row);
        }

        uasort($rows, static function (array $a, array $b) use ($query): int {
            $left = is_scalar($a[$query->orderBy]) ? (string) $a[$query->orderBy] : '';
            $right = is_scalar($b[$query->orderBy]) ? (string) $b[$query->orderBy] : '';

            return $left <=> $right;
        });

        if ($query->limitToFirst !== null) {
            $rows = array_slice($rows, 0, $query->limitToFirst, true);
        }

        if ($query->limitToLast !== null) {
            $rows = array_slice($rows, -$query->limitToLast, null, true);
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

    /**
     * Seed a node without stamping `updatedAt` — for arranging a starting state.
     *
     * @param  array<string, mixed>  $data
     */
    public function seed(string $path, array $data): void
    {
        $this->write($this->segments($path), $data);
    }

    /**
     * Re-key an RTDB node so PHPStan sees the string-keyed shape RTDB promises.
     *
     * @param  array<mixed, mixed>  $row
     * @return array<string, mixed>
     */
    private function stringKeyed(array $row): array
    {
        $normalized = [];

        foreach ($row as $key => $value) {
            $normalized[(string) $key] = $value;
        }

        return $normalized;
    }

    /**
     * @return list<string>
     */
    private function segments(string $path): array
    {
        return $this->pathSegments($path);
    }

    /**
     * @param  list<string>  $segments
     */
    private function read(array $segments): mixed
    {
        $node = $this->store;

        foreach ($segments as $segment) {
            if (! is_array($node) || ! array_key_exists($segment, $node)) {
                return null;
            }

            $node = $node[$segment];
        }

        return $node;
    }

    /**
     * @param  list<string>  $segments
     */
    private function write(array $segments, mixed $value): void
    {
        $node = &$this->reference($segments);
        $node = $value;
    }

    /**
     * A reference to the node at $segments, creating intermediate arrays.
     *
     * @param  list<string>  $segments
     */
    private function &reference(array $segments): mixed
    {
        $node = &$this->store;

        foreach ($segments as $segment) {
            if (! is_array($node)) {
                $node = [];
            }

            if (! array_key_exists($segment, $node)) {
                $node[$segment] = [];
            }

            $node = &$node[$segment];
        }

        return $node;
    }
}
