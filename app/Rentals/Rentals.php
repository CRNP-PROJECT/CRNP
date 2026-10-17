<?php

declare(strict_types=1);

namespace App\Rentals;

use App\Rtdb\Contracts\RtdbClient;

/**
 * Reads the rentable items at `rentals/{id}` (CONTEXT — *Rental stock*).
 *
 * Inventory is rental units only, so this is the one tree the admin's rental
 * inventory surface and the public catalog both read. A row that cannot be
 * parsed is skipped rather than rendered as a half-row.
 */
final readonly class Rentals
{
    public function __construct(private RtdbClient $client) {}

    /**
     * Every rental item, keyed by id.
     *
     * @return array<string, RentalItem>
     */
    public function all(): array
    {
        return self::itemsFromNode($this->client->get('rentals'));
    }

    public function find(string $id): ?RentalItem
    {
        $node = $this->client->get("rentals/{$id}");

        return is_array($node) ? RentalItem::fromNode($id, $node) : null;
    }

    /**
     * Map a raw `rentals` node to value objects, skipping a row that cannot be
     * parsed.
     *
     * @return array<string, RentalItem>
     */
    public static function itemsFromNode(mixed $node): array
    {
        if (! is_array($node)) {
            return [];
        }

        $items = [];

        foreach ($node as $id => $row) {
            if (! is_string($id) || ! is_array($row)) {
                continue;
            }

            $item = RentalItem::fromNode($id, $row);

            if ($item !== null) {
                $items[$id] = $item;
            }
        }

        return $items;
    }
}
