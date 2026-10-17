<?php

declare(strict_types=1);

namespace App\Catalog;

/**
 * A product read from `products/{id}` — the menu item the admin manages.
 *
 * A product carries no stock: food is made to order, so sold-out is the
 * `hidden` toggle (the 86 board), never a falling counter (PLAN §4 *Inventory*).
 * `imageBase64` holds the server-resized JPEG, never a raw upload.
 */
final readonly class Product
{
    /**
     * @param  list<AddOn>  $addOns
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $description,
        public float $price,
        public string $categoryId,
        public string $imageBase64,
        public bool $hidden,
        public ?Promo $promo,
        public array $addOns,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
        public ?string $theme = null,
    ) {}

    /**
     * @param  array<mixed, mixed>  $node
     */
    public static function fromNode(string $id, array $node): ?self
    {
        $name = $node['name'] ?? null;
        $price = $node['price'] ?? null;

        if (! is_string($name) || $name === '' || ! is_numeric($price)) {
            return null;
        }

        $categoryId = $node['categoryId'] ?? '';
        $description = $node['description'] ?? '';
        $imageBase64 = $node['imageBase64'] ?? '';
        $createdAt = $node['createdAt'] ?? null;
        $updatedAt = $node['updatedAt'] ?? null;
        $theme = $node['theme'] ?? null;

        return new self(
            id: $id,
            name: $name,
            description: is_string($description) ? $description : '',
            price: (float) $price,
            categoryId: is_string($categoryId) ? $categoryId : '',
            imageBase64: is_string($imageBase64) ? $imageBase64 : '',
            hidden: ($node['hidden'] ?? false) === true,
            promo: self::promoFromNode($node, (float) $price),
            addOns: self::addOnsFromNode($node['addOns'] ?? null),
            createdAt: is_string($createdAt) ? $createdAt : null,
            updatedAt: is_string($updatedAt) ? $updatedAt : null,
            theme: is_string($theme) && $theme !== '' ? $theme : null,
        );
    }

    /**
     * The effective price a customer pays: the promo price when a promo is set,
     * otherwise the base price.
     */
    public function sellingPrice(): float
    {
        return $this->promo !== null ? $this->promo->promoPrice : $this->price;
    }

    /**
     * @param  array<mixed, mixed>  $node
     */
    private static function promoFromNode(array $node, float $price): ?Promo
    {
        $mode = $node['promoMode'] ?? null;
        $value = $node['promoValue'] ?? null;
        $promoPrice = $node['promoPrice'] ?? null;

        if (! is_string($mode) || ! is_numeric($value) || ! is_numeric($promoPrice)) {
            return null;
        }

        $promoMode = PromoMode::tryFrom($mode);

        if ($promoMode === null) {
            return null;
        }

        return Promo::stored($promoMode, (float) $value, $price, (float) $promoPrice);
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
