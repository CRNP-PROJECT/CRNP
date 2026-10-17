<?php

declare(strict_types=1);

namespace App\Orders;

use App\Catalog\AddOn;

/**
 * One line on an order: the product snapshot taken when it was rung up.
 *
 * The unit price is the product's **selling price** at ring-up (the promo price
 * when a promo is set), frozen onto the line so the receipt and the kitchen
 * ticket can never disagree with each other or drift when the admin edits the
 * menu afterwards. Add-ons ride on the line through to kitchen and receipt.
 */
final readonly class LineItem
{
    /**
     * @param  list<AddOn>  $addOns
     */
    public function __construct(
        public string $productId,
        public string $name,
        public float $unitPrice,
        public int $quantity,
        public array $addOns = [],
    ) {}

    public function lineTotal(): float
    {
        $addOnTotal = 0.0;

        foreach ($this->addOns as $addOn) {
            $addOnTotal += $addOn->price;
        }

        return round(($this->unitPrice + $addOnTotal) * $this->quantity, 2);
    }

    /**
     * The sum of a set of lines, in integer centavos so no floating-point drift
     * creeps in — the one rule the cart total and the walk-in ring-up total
     * share.
     *
     * @param  list<LineItem>  $lines
     */
    public static function sum(array $lines): float
    {
        $cents = 0;

        foreach ($lines as $line) {
            $cents += (int) round($line->lineTotal() * 100);
        }

        return $cents / 100;
    }

    /**
     * @param  array<mixed, mixed>  $node
     */
    public static function fromNode(array $node): ?self
    {
        $productId = $node['productId'] ?? null;
        $name = $node['name'] ?? null;
        $unitPrice = $node['unitPrice'] ?? null;
        $quantity = $node['quantity'] ?? null;

        if (! is_string($productId) || ! is_string($name) || ! is_numeric($unitPrice) || ! is_numeric($quantity)) {
            return null;
        }

        return new self(
            productId: $productId,
            name: $name,
            unitPrice: (float) $unitPrice,
            quantity: (int) $quantity,
            addOns: self::addOnsFromNode($node['addOns'] ?? null),
        );
    }

    /**
     * @return array{productId: string, name: string, unitPrice: float, quantity: int, addOns: list<array{name: string, price: float}>}
     */
    public function toNode(): array
    {
        return [
            'productId' => $this->productId,
            'name' => $this->name,
            'unitPrice' => $this->unitPrice,
            'quantity' => $this->quantity,
            'addOns' => array_map(static fn(AddOn $addOn): array => $addOn->toNode(), $this->addOns),
        ];
    }

    /**
     * @return list<AddOn>
     */
    private static function addOnsFromNode(mixed $node): array
    {
        if (! is_array($node)) {
            return [];
        }

        $addOns = [];

        foreach ($node as $row) {
            if (! is_array($row)) {
                continue;
            }

            $addOn = AddOn::fromNode($row);

            if ($addOn !== null) {
                $addOns[] = $addOn;
            }
        }

        return $addOns;
    }
}
