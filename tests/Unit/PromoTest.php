<?php

declare(strict_types=1);

use App\Catalog\Promo;
use App\Catalog\PromoMode;

it('derives the promo price from a discount percent', function (): void {
    $promo = Promo::derive(PromoMode::Percent, 20.0, 250.0);

    expect($promo->promoPrice)->toBe(200.0)
        ->and($promo->label())->toBe('20% off');
});

it('derives the discount percent from an exact promo price', function (): void {
    $promo = Promo::derive(PromoMode::Amount, 200.0, 250.0);

    expect($promo->percentOff())->toBe(20)
        ->and($promo->label())->toBe('20% off');
});

it('rounds a fractional discount percent to the nearest whole percent', function (): void {
    // (250 - 199) / 250 = 20.4% -> 20%
    $promo = Promo::derive(PromoMode::Amount, 199.0, 250.0);

    expect($promo->percentOff())->toBe(20)
        ->and($promo->label())->toBe('20% off');
});

it('rounds a derived promo price to the nearest centavo', function (): void {
    // 300 * (1 - 33/100) = 201
    $promo = Promo::derive(PromoMode::Percent, 33.0, 300.0);

    expect($promo->promoPrice)->toBe(201.0);
});

it('rejects a promo that does not discount the base price', function (): void {
    expect(fn() => Promo::derive(PromoMode::Percent, 0.0, 250.0))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn() => Promo::derive(PromoMode::Percent, 100.0, 250.0))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn() => Promo::derive(PromoMode::Amount, 250.0, 250.0))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn() => Promo::derive(PromoMode::Amount, 300.0, 250.0))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn() => Promo::derive(PromoMode::Amount, -5.0, 250.0))
        ->toThrow(InvalidArgumentException::class);
});

it('re-derives the promo price when the base price changes', function (): void {
    $promo = Promo::derive(PromoMode::Percent, 20.0, 250.0);
    expect($promo->promoPrice)->toBe(200.0);

    $repriced = Promo::derive($promo->mode, $promo->value, 300.0);

    expect($repriced->promoPrice)->toBe(240.0)
        ->and($repriced->label())->toBe('20% off');
});
