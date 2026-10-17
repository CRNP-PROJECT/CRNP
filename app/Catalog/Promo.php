<?php

declare(strict_types=1);

namespace App\Catalog;

use InvalidArgumentException;

/**
 * A product's promo, derived from the base price and the one number the admin
 * typed.
 *
 * The admin supplies *either* a discount percent (`PromoMode::Percent`) *or* an
 * exact promo price (`PromoMode::Amount`); the sibling value and the "% off"
 * label are computed here, never typed. A promo is only valid when
 * `0 < promoPrice < price`, so a full-price or negative discount is refused at
 * the boundary.
 */
final readonly class Promo
{
    private function __construct(
        public PromoMode $mode,
        public float $value,
        public float $price,
        public float $promoPrice,
    ) {}

    /**
     * Derive a promo from the admin's mode + number against the base price.
     *
     * @throws InvalidArgumentException when the derived promo price is not
     *                                  strictly between zero and the base price.
     */
    public static function derive(PromoMode $mode, float $value, float $price): self
    {
        $promoPrice = match ($mode) {
            PromoMode::Percent => round($price * (1 - $value / 100), 2),
            PromoMode::Amount => round($value, 2),
        };

        if ($promoPrice <= 0 || $promoPrice >= $price) {
            throw new InvalidArgumentException(
                'A promo price must be greater than zero and less than the base price.',
            );
        }

        return new self($mode, $value, $price, $promoPrice);
    }

    /**
     * Rebuild a promo from the values already stored on a product node, without
     * re-validating — reads must never throw on legacy data.
     */
    public static function stored(PromoMode $mode, float $value, float $price, float $promoPrice): self
    {
        return new self($mode, $value, $price, $promoPrice);
    }

    /**
     * The "% off" figure, computed from the discount ratio.
     */
    public function percentOff(): int
    {
        $percent = match ($this->mode) {
            PromoMode::Percent => $this->value,
            PromoMode::Amount => $this->price > 0
                ? ($this->price - $this->promoPrice) / $this->price * 100
                : 0.0,
        };

        return (int) round($percent);
    }

    public function label(): string
    {
        return $this->percentOff() . '% off';
    }
}
