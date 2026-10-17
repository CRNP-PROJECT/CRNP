<?php

declare(strict_types=1);

namespace App\Rtdb;

use InvalidArgumentException;

/**
 * Guards every path segment against RTDB's forbidden node keys (`. $ # [ ] /`
 * and control characters) before it is placed in a URL or a store.
 *
 * Firebase silently rejects or mangles such keys, so the failure is surfaced
 * here instead of corrupting a path.
 */
trait ValidatesNodeKeys
{
    /**
     * @param  list<string>  $segments
     */
    private function assertValidPath(array $segments): void
    {
        foreach ($segments as $segment) {
            if ($segment === '' || preg_match('/[.#$\[\]\/\x00-\x1F\x7F]/', $segment) === 1) {
                throw new InvalidArgumentException("RTDB forbids the node key [{$segment}].");
            }
        }
    }

    /**
     * @return list<string>
     */
    private function pathSegments(string $path): array
    {
        $trimmed = trim($path, '/');

        if ($trimmed === '') {
            return [];
        }

        $segments = explode('/', $trimmed);

        $this->assertValidPath($segments);

        return $segments;
    }
}
