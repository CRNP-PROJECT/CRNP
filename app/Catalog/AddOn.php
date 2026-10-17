<?php

declare(strict_types=1);

namespace App\Catalog;

/**
 * A per-product add-on: a name the customer sees and a price it adds to the
 * line item. Add-ons are admin-managed and ride on the order line item through
 * to the kitchen ticket and receipt.
 */
final readonly class AddOn
{
    public function __construct(
        public string $name,
        public float $price,
    ) {}

    /**
     * @param  array<mixed, mixed>  $node
     */
    public static function fromNode(array $node): ?self
    {
        $name = $node['name'] ?? null;
        $price = $node['price'] ?? null;

        if (! is_string($name) || $name === '' || ! is_numeric($price)) {
            return null;
        }

        return new self($name, (float) $price);
    }

    /**
     * @return array{name: string, price: float}
     */
    public function toNode(): array
    {
        return ['name' => $this->name, 'price' => $this->price];
    }
}
