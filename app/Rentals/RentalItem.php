<?php

declare(strict_types=1);

namespace App\Rentals;

/**
 * A rentable item read from `rentals/{id}` — the inventory row the admin
 * manages (CONTEXT — *Rental stock*; PLAN §4 *Admin*).
 *
 * Inventory is rental units only: menu items carry no stock, so a falling
 * counter can never manufacture a false sold-out. `units` is the **on-hand**
 * count: it rises via **Add stock** (a receiving entry) and **Confirm return**,
 * and falls at **Hand over**. `imageBase64` holds the server-resized JPEG, never
 * a raw upload. `pricePerDay` is the whole-day rate the booking total computes
 * from.
 */
final readonly class RentalItem
{
    public function __construct(
        public string $id,
        public string $name,
        public string $imageBase64,
        public float $pricePerDay,
        public int $units,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {}

    /**
     * @param  array<mixed, mixed>  $node
     */
    public static function fromNode(string $id, array $node): ?self
    {
        $name = $node['name'] ?? null;
        $pricePerDay = $node['pricePerDay'] ?? null;

        if (! is_string($name) || $name === '' || ! is_numeric($pricePerDay)) {
            return null;
        }

        $image = $node['imageBase64'] ?? '';
        $units = $node['units'] ?? 0;
        $createdAt = $node['createdAt'] ?? null;
        $updatedAt = $node['updatedAt'] ?? null;

        return new self(
            id: $id,
            name: $name,
            imageBase64: is_string($image) ? $image : '',
            pricePerDay: (float) $pricePerDay,
            units: is_numeric($units) ? (int) $units : 0,
            createdAt: is_string($createdAt) ? $createdAt : null,
            updatedAt: is_string($updatedAt) ? $updatedAt : null,
        );
    }
}
