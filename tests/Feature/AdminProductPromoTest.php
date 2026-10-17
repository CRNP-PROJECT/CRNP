<?php

declare(strict_types=1);

use App\Cache\CatalogCache;
use App\Catalog\Catalog;
use App\Staff\StaffRole;

beforeEach(function (): void {
    seedStaff('owner@example.com', StaffRole::Admin, 'Owner Santos');
    seedCategory('drinks', 'Drinks');

    signIn('owner@example.com');
});

it('adds a percent promo and computes the sibling price and label', function (): void {
    seedProduct('p1', ['price' => 250.0]);

    $this->post('/admin/products/p1/promo', ['mode' => 'percent', 'value' => '20'])
        ->assertRedirect(route('admin.products.index'));

    expect(rtdb()->get('products/p1'))->toMatchArray([
        'promoMode' => 'percent',
        'promoValue' => 20.0,
        'promoPrice' => 200.0,
    ]);

    $product = app(Catalog::class)->product('p1');

    expect($product)->not->toBeNull()
        ->and($product->promo?->label())->toBe('20% off')
        ->and($product->sellingPrice())->toBe(200.0);
});

it('adds an exact-price promo and computes the sibling percent and label', function (): void {
    seedProduct('p1', ['price' => 250.0]);

    $this->post('/admin/products/p1/promo', ['mode' => 'amount', 'value' => '200'])
        ->assertRedirect(route('admin.products.index'));

    expect(rtdb()->get('products/p1'))->toMatchArray([
        'promoMode' => 'amount',
        'promoValue' => 200.0,
        'promoPrice' => 200.0,
    ]);

    expect(app(Catalog::class)->product('p1')->promo?->label())->toBe('20% off');
});

it('rejects a promo that does not discount the base price', function (): void {
    seedProduct('p1', ['price' => 250.0]);

    $this->post('/admin/products/p1/promo', ['mode' => 'amount', 'value' => '250'])
        ->assertSessionHasErrors('value');

    $this->post('/admin/products/p1/promo', ['mode' => 'percent', 'value' => '0'])
        ->assertSessionHasErrors('value');

    $this->post('/admin/products/p1/promo', ['mode' => 'amount', 'value' => '300'])
        ->assertSessionHasErrors('value');

    expect(rtdb()->get('products/p1'))->not->toHaveKey('promoMode');
});

it('re-derives the promo price when the base price is edited', function (): void {
    seedProduct('p1', ['price' => 250.0]);

    $this->post('/admin/products/p1/promo', ['mode' => 'percent', 'value' => '20']);

    $this->put('/admin/products/p1', [
        'name' => 'Milktea',
        'description' => 'Classic',
        'price' => '300',
        'categoryId' => 'drinks',
    ])->assertRedirect(route('admin.products.index'));

    expect(rtdb()->get('products/p1'))->toMatchArray([
        'price' => 300.0,
        'promoMode' => 'percent',
        'promoValue' => 20.0,
        'promoPrice' => 240.0,
    ]);
});

it('drops a promo a new base price can no longer honour', function (): void {
    seedProduct('p1', ['price' => 250.0]);
    $this->post('/admin/products/p1/promo', ['mode' => 'amount', 'value' => '200']);

    // A ₱150 base price cannot hold a ₱200 promo price.
    $this->put('/admin/products/p1', [
        'name' => 'Milktea',
        'description' => 'Classic',
        'price' => '150',
        'categoryId' => 'drinks',
    ])->assertRedirect(route('admin.products.index'));

    $node = rtdb()->get('products/p1');

    expect($node)->toMatchArray(['price' => 150.0])
        ->and($node)->not->toHaveKey('promoMode');
});

it('removes a promo in one click', function (): void {
    seedProduct('p1', ['price' => 250.0]);
    $this->post('/admin/products/p1/promo', ['mode' => 'percent', 'value' => '20']);

    $this->delete('/admin/products/p1/promo')->assertRedirect(route('admin.products.index'));

    expect(rtdb()->get('products/p1'))->not->toHaveKey('promoMode');
});

it('evicts the catalog cache when a promo is added', function (): void {
    seedProduct('p1', ['price' => 250.0]);

    rtdb()->cached(CatalogCache::KEY, 'products', 300);

    $this->post('/admin/products/p1/promo', ['mode' => 'percent', 'value' => '20']);

    expect(Cache::get(CatalogCache::KEY))->toBeNull();
});

it('shows the live promo preview on the product form', function (): void {
    seedProduct('p1', ['price' => 250.0]);
    $this->post('/admin/products/p1/promo', ['mode' => 'percent', 'value' => '20']);

    $this->get('/admin/products/p1/edit')
        ->assertOk()
        ->assertSee('20% off')
        ->assertSee('200');
});
