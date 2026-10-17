<?php

declare(strict_types=1);

namespace App\Rtdb\Rules;

use RuntimeException;

/**
 * Reads `database.rules.json` so the data-layer smoke test can assert that
 * every queried field is declared with `.indexOn`, and that reads and writes
 * require an authenticated service account.
 *
 * This is a fixture, not a rules engine: it parses only the `.read` / `.write`
 * gates and the `.indexOn` lists the app depends on.
 */
final readonly class RulesFixture
{
    /**
     * @param  array<string, mixed>  $rules
     */
    private function __construct(private array $rules) {}

    public static function fromFile(string $path): self
    {
        if (! is_file($path)) {
            throw new RuntimeException("The rules fixture was not found at [{$path}].");
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('The rules fixture is not valid JSON.');
        }

        $rules = $decoded['rules'] ?? null;

        if (! is_array($rules)) {
            throw new RuntimeException('The rules fixture is missing a top-level "rules" object.');
        }

        $normalized = [];

        foreach ($rules as $key => $value) {
            $normalized[(string) $key] = $value;
        }

        return new self($normalized);
    }

    public function read(): string
    {
        $read = $this->rules['.read'] ?? '';

        return is_string($read) ? $read : '';
    }

    public function write(): string
    {
        $write = $this->rules['.write'] ?? '';

        return is_string($write) ? $write : '';
    }

    /**
     * The `.indexOn` fields declared for a path, or an empty list when none.
     *
     * @return list<string>
     */
    public function indexes(string $path): array
    {
        $node = $this->rules;

        foreach (explode('/', trim($path, '/')) as $segment) {
            if (! is_array($node) || ! array_key_exists($segment, $node)) {
                return [];
            }

            $node = $node[$segment];
        }

        if (! is_array($node)) {
            return [];
        }

        $indexOn = $node['.indexOn'] ?? null;

        if (! is_array($indexOn)) {
            return [];
        }

        $fields = [];

        foreach ($indexOn as $field) {
            if (is_string($field)) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * Assert a field is indexed before a query is issued against it.
     */
    public function assertIndexed(string $path, string $field): void
    {
        if (! in_array($field, $this->indexes($path), true)) {
            throw new RuntimeException("No .indexOn declared for [{$path}.{$field}].");
        }
    }
}
