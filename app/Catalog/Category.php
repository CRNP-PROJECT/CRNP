<?php

declare(strict_types=1);

namespace App\Catalog;

/**
 * A menu category: the admin-managed grouping products reference by id.
 *
 * Stored at `categories/{id}` with a name and an explicit sort order so the
 * public menu lists categories the admin's way rather than by key.
 */
final readonly class Category
{
    public function __construct(
        public string $id,
        public string $name,
        public int $order,
    ) {}

    /**
     * @param  array<mixed, mixed>  $node
     */
    public static function fromNode(string $id, array $node): ?self
    {
        $name = $node['name'] ?? null;

        if (! is_string($name) || $name === '') {
            return null;
        }

        $order = $node['order'] ?? 0;

        return new self($id, $name, is_numeric($order) ? (int) $order : 0);
    }

    /**
     * @return array{name: string, order: int}
     */
    public function toNode(): array
    {
        return ['name' => $this->name, 'order' => $this->order];
    }
}
