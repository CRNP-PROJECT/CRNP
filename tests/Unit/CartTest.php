<?php

declare(strict_types=1);

use App\Catalog\Product;
use App\Orders\Cart;

/**
 * #23 — the cart computes totals from steppers and add-ons.
 *
 * The cart is a server-side value object: it is built from the session
 * selection and the current catalog, so the client never supplies a price.
 * A line freezes the product's selling price (the promo price when a promo is
 * set) and the chosen add-ons' prices, exactly as the receipt and kitchen
 * ticket will read them.
 */

/**
 * A catalog product with the given add-ons and optional promo, as `fromNode`
 * expects it.
 *
 * @param  array<string, float>  $addOns
 */
function cartProduct(string $id, float $price, array $addOns = [], ?float $promoPrice = null): Product
{
    $rows = [];

    foreach ($addOns as $name => $amount) {
        $rows[] = ['name' => $name, 'price' => $amount];
    }

    $node = [
        'name' => 'Milktea',
        'price' => $price,
        'addOns' => $rows,
    ];

    if ($promoPrice !== null) {
        $node['promoMode'] = 'amount';
        $node['promoValue'] = $promoPrice;
        $node['promoPrice'] = $promoPrice;
    }

    $product = Product::fromNode($id, $node);

    expect($product)->toBeInstanceOf(Product::class);

    return $product;
}

it('computes a line total from the stepper quantity and the add-on prices', function (): void {
    $product = cartProduct('p1', 200.0, ['Pearl' => 15.0, 'Less ice' => 0.0]);

    $cart = Cart::fromInput(
        ['p1' => ['quantity' => 2, 'addOns' => ['Pearl']]],
        ['p1' => $product],
    );

    // (200 + 15) * 2 = 430
    expect($cart->total())->toBe(430.0)
        ->and($cart->lines())->toHaveCount(1)
        ->and($cart->lines()[0]->addOns)->toHaveCount(1);
});

it('uses the promo selling price for a line', function (): void {
    $product = cartProduct('p1', 250.0, [], promoPrice: 200.0);

    $cart = Cart::fromInput(['p1' => ['quantity' => 3, 'addOns' => []]], ['p1' => $product]);

    expect($cart->total())->toBe(600.0);
});

it('ignores an add-on the product does not offer', function (): void {
    $product = cartProduct('p1', 200.0, ['Pearl' => 15.0]);

    $cart = Cart::fromInput(
        ['p1' => ['quantity' => 1, 'addOns' => ['Pearl', 'Ghost']]],
        ['p1' => $product],
    );

    expect($cart->total())->toBe(215.0)
        ->and($cart->lines()[0]->addOns)->toHaveCount(1);
});

it('skips a session row whose product is no longer in the catalog', function (): void {
    $product = cartProduct('p1', 200.0);

    $cart = Cart::fromInput(
        [
            'p1' => ['quantity' => 1, 'addOns' => []],
            'gone' => ['quantity' => 5, 'addOns' => []],
        ],
        ['p1' => $product],
    );

    expect($cart->lines())->toHaveCount(1)
        ->and($cart->total())->toBe(200.0);
});

it('round-trips the selection back to the session shape', function (): void {
    $product = cartProduct('p1', 200.0, ['Pearl' => 15.0]);

    $cart = Cart::fromInput(['p1' => ['quantity' => 2, 'addOns' => ['Pearl']]], ['p1' => $product]);

    expect($cart->toSession())->toBe([
        'p1' => ['quantity' => 2, 'addOns' => ['Pearl']],
    ]);
});

it('is empty when the session holds nothing', function (): void {
    expect(Cart::fromInput(null, [])->isEmpty())->toBeTrue()
        ->and(Cart::fromInput([], [])->total())->toBe(0.0);
});
