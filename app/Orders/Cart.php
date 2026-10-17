<?php

declare(strict_types=1);

namespace App\Orders;

use App\Catalog\AddOn;
use App\Catalog\Product;

/**
 * The customer's cart for one checkout attempt (PLAN §4 *Customer site* — cart
 * → checkout).
 *
 * The cart is rebuilt server-side from the session selection and the current
 * catalog on every request, so a customer can never supply a price: a line
 * freezes the product's **selling price** (the promo price when a promo is set)
 * and the chosen add-ons' prices, exactly as the receipt and kitchen ticket
 * will read them. The selection is stored in the session as
 * `productId => {quantity, addOns[]}` so the steppers and add-on picks survive a
 * refresh without an edit form.
 *
 * Totals run through integer centavos, so the figure the customer sees, the
 * order total the server persists, and the payment window all agree.
 */
final readonly class Cart
{
    /**
     * @param  list<LineItem>  $lines
     * @param  array<string, array{quantity: int, addOns: list<string>}>  $selection
     */
    private function __construct(
        private array $lines,
        private array $selection,
    ) {}

    /**
     * Build a cart from the raw session selection and the catalog, keyed by id.
     *
     * A row whose product is gone from the catalog (hidden or deleted) is
     * dropped rather than rendered as a half-line, and an add-on the product no
     * longer offers is ignored, so a stale session can never invent a price.
     *
     * @param  array<string, Product>  $products
     */
    public static function fromInput(mixed $raw, array $products): self
    {
        if (! is_array($raw)) {
            return new self([], []);
        }

        $lines = [];
        $selection = [];

        foreach ($raw as $productId => $row) {
            if (! is_string($productId) || ! is_array($row)) {
                continue;
            }

            $product = $products[$productId] ?? null;

            if (! $product instanceof Product) {
                continue;
            }

            $quantity = self::quantity($row['quantity'] ?? null);

            if ($quantity < 1) {
                continue;
            }

            $chosen = self::addOnNames($row['addOns'] ?? null);
            $addOns = self::offered($product, $chosen);

            $lines[] = new LineItem(
                productId: $product->id,
                name: $product->name,
                unitPrice: $product->sellingPrice(),
                quantity: $quantity,
                addOns: $addOns,
            );

            $selection[$product->id] = [
                'quantity' => $quantity,
                'addOns' => array_map(static fn(AddOn $addOn): string => $addOn->name, $addOns),
            ];
        }

        return new self($lines, $selection);
    }

    /**
     * @return list<LineItem>
     */
    public function lines(): array
    {
        return $this->lines;
    }

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }

    public function total(): float
    {
        return LineItem::sum($this->lines);
    }

    /**
     * The normalized selection to persist back into the session.
     *
     * @return array<string, array{quantity: int, addOns: list<string>}>
     */
    public function toSession(): array
    {
        return $this->selection;
    }

    private static function quantity(mixed $value): int
    {
        if (! is_numeric($value)) {
            return 0;
        }

        return max(0, min(99, (int) $value));
    }

    /**
     * @return list<string>
     */
    private static function addOnNames(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $names = [];

        foreach ($raw as $name) {
            if (is_string($name) && trim($name) !== '') {
                $names[] = trim($name);
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * The product's add-ons the customer selected, in the product's order.
     *
     * @param  list<string>  $chosen
     * @return list<AddOn>
     */
    private static function offered(Product $product, array $chosen): array
    {
        $addOns = [];

        foreach ($product->addOns as $addOn) {
            if (in_array($addOn->name, $chosen, true)) {
                $addOns[] = $addOn;
            }
        }

        return $addOns;
    }
}
