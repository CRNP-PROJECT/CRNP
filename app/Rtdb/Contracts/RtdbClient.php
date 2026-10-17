<?php

declare(strict_types=1);

namespace App\Rtdb\Contracts;

use App\Rtdb\RtdbQuery;

/**
 * The single persistence boundary for the app: Firebase Realtime Database.
 *
 * Every read and write in the system goes through this contract, so tests can
 * substitute an in-memory implementation without a network or credentials.
 */
interface RtdbClient
{
    /**
     * Read the node at $path, or null when the node does not exist.
     *
     * A Firebase `{"error": ...}` body is logged and dropped as an empty read
     * rather than returned as data.
     */
    public function get(string $path): mixed;

    /**
     * Replace the node at $path with $data and stamp `updatedAt`.
     *
     * @param  array<string, mixed>  $data
     */
    public function set(string $path, array $data): mixed;

    /**
     * Merge $data into the named children of the node at $path and stamp
     * `updatedAt`, leaving siblings intact.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(string $path, array $data): mixed;

    /**
     * Remove the node at $path.
     */
    public function delete(string $path): void;

    /**
     * Run a bounded query declared with `.indexOn` on the queried field.
     *
     * @return array<string, array<string, mixed>>
     */
    public function query(string $path, RtdbQuery $query): array;

    /**
     * Serve the node at $path from the cache, re-reading at most once per
     * $ttlSeconds — the caller's poll cadence. The entry is never older than
     * that cadence.
     */
    public function cached(string $key, string $path, int $ttlSeconds): mixed;

    /**
     * Evict a cached read so its own writes propagate on the next request.
     */
    public function forget(string $key): void;
}
