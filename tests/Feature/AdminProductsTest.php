<?php

declare(strict_types=1);

use App\Cache\CatalogCache;
use App\Catalog\Catalog;
use App\Staff\StaffRole;
use Illuminate\Http\UploadedFile;

beforeEach(function (): void {
    seedStaff('owner@example.com', StaffRole::Admin, 'Owner Santos');

    signIn('owner@example.com');
});

it('creates a product with a server-resized base64 image', function (): void {
    seedCategory('drinks', 'Drinks');

    $this->post('/admin/products', [
        'name' => 'Milktea',
        'description' => 'Classic milk tea',
        'price' => '120',
        'categoryId' => 'drinks',
        'image' => UploadedFile::fake()->image('milktea.jpg', 1600, 1200),
    ])->assertRedirect(route('admin.products.index'));

    $product = onlyProduct();

    expect($product['node'])->toMatchArray([
        'name' => 'Milktea',
        'description' => 'Classic milk tea',
        'price' => 120.0,
        'categoryId' => 'drinks',
        'hidden' => false,
    ]);

    $image = base64_decode((string) $product['node']['imageBase64'], true);

    expect($image)->not->toBeFalse();

    $info = getimagesizefromstring((string) $image);

    expect($info)->not->toBeFalse()
        ->and($info[2])->toBe(IMAGETYPE_JPEG)
        ->and(max($info[0], $info[1]))->toBeLessThanOrEqual(800)
        ->and(strlen((string) $image))->toBeLessThanOrEqual(300 * 1024);
});

it('evicts the catalog cache when a product is written', function (): void {
    seedCategory('drinks', 'Drinks');

    rtdb()->cached(CatalogCache::KEY, 'products', 300);

    $this->post('/admin/products', [
        'name' => 'Milktea',
        'description' => 'Classic',
        'price' => '120',
        'categoryId' => 'drinks',
        'image' => UploadedFile::fake()->image('milktea.jpg', 1600, 1200),
    ]);

    expect(Cache::get(CatalogCache::KEY))->toBeNull();
});

it('rejects a product with no name', function (): void {
    seedCategory('drinks', 'Drinks');

    $this->post('/admin/products', [
        'name' => '',
        'price' => '120',
        'categoryId' => 'drinks',
    ])->assertSessionHasErrors('name');

    expect(rtdb()->get('products'))->toBeNull();
});

it('persists per-product add-ons with a name and price', function (): void {
    seedCategory('drinks', 'Drinks');

    $this->post('/admin/products', [
        'name' => 'Milktea',
        'description' => 'Classic',
        'price' => '120',
        'categoryId' => 'drinks',
        'image' => UploadedFile::fake()->image('milktea.jpg'),
        'addOns' => [
            ['name' => 'Pearl', 'price' => '15'],
            ['name' => 'Less ice', 'price' => '0'],
        ],
    ])->assertRedirect(route('admin.products.index'));

    $product = onlyProduct();

    expect(array_values($product['node']['addOns']))->toBe([
        ['name' => 'Pearl', 'price' => 15.0],
        ['name' => 'Less ice', 'price' => 0.0],
    ]);
});

it('persists more than three add-ons on one product', function (): void {
    seedCategory('drinks', 'Drinks');

    $addOns = [];

    foreach (range(1, 6) as $number) {
        $addOns[] = ['name' => "Extra {$number}", 'price' => '5'];
    }

    $this->post('/admin/products', [
        'name' => 'Milktea',
        'description' => 'Classic',
        'price' => '120',
        'categoryId' => 'drinks',
        'image' => UploadedFile::fake()->image('milktea.jpg'),
        'addOns' => $addOns,
    ])->assertRedirect(route('admin.products.index'));

    expect(array_values(onlyProduct()['node']['addOns']))->toHaveCount(6);
});

it('renders a row per stored add-on plus the add-another control', function (): void {
    seedCategory('drinks', 'Drinks');
    seedProduct('p1', [
        'addOns' => [
            ['name' => 'Pearl', 'price' => 15.0],
            ['name' => 'Grass jelly', 'price' => 10.0],
            ['name' => 'Less ice', 'price' => 0.0],
            ['name' => 'Extra shot', 'price' => 20.0],
        ],
    ]);

    $this->get('/admin/products/p1/edit')
        ->assertOk()
        ->assertSee('name="addOns[3][name]"', false)
        ->assertSee('value="Extra shot"', false)
        ->assertSee('data-addon-add', false)
        ->assertSee('data-addon-template', false)
        ->assertSee('Up to 20 add-ons');
});

it('drops a blank add-on row instead of storing it', function (): void {
    seedCategory('drinks', 'Drinks');

    $this->post('/admin/products', [
        'name' => 'Milktea',
        'description' => 'Classic',
        'price' => '120',
        'categoryId' => 'drinks',
        'image' => UploadedFile::fake()->image('milktea.jpg'),
        'addOns' => [
            ['name' => 'Pearl', 'price' => '15'],
            ['name' => '', 'price' => ''],
        ],
    ])->assertRedirect(route('admin.products.index'));

    $product = onlyProduct();

    expect(array_values($product['node']['addOns']))->toBe([
        ['name' => 'Pearl', 'price' => 15.0],
    ]);
});

it('hides a product from the menu with one tap and shows it again', function (): void {
    seedCategory('drinks', 'Drinks');
    seedProduct('p1', ['hidden' => false]);

    $this->put('/admin/products/p1/visibility', ['hidden' => '1'])
        ->assertRedirect(route('admin.products.index'));

    expect(rtdb()->get('products/p1'))->toMatchArray(['hidden' => true])
        ->and(app(Catalog::class)->menu())->toBe([]);

    $this->put('/admin/products/p1/visibility', ['hidden' => '0'])
        ->assertRedirect(route('admin.products.index'));

    expect(rtdb()->get('products/p1'))->toMatchArray(['hidden' => false])
        ->and(array_keys(app(Catalog::class)->menu()))->toBe(['p1']);
});

it('evicts the catalog cache when the hide toggle is flipped', function (): void {
    seedProduct('p1');

    rtdb()->cached(CatalogCache::KEY, 'products', 300);

    $this->put('/admin/products/p1/visibility', ['hidden' => '1']);

    expect(Cache::get(CatalogCache::KEY))->toBeNull();
});

it('ignores promo fields posted to the promo-free product form', function (): void {
    seedCategory('drinks', 'Drinks');
    seedProduct('p1');

    $this->put('/admin/products/p1', [
        'name' => 'Milktea',
        'description' => 'Classic',
        'price' => '120',
        'categoryId' => 'drinks',
        'promoMode' => 'percent',
        'promoValue' => '20',
        'promoPrice' => '96',
    ])->assertRedirect(route('admin.products.index'));

    expect(rtdb()->get('products/p1'))->not->toHaveKey('promoMode');
});

it('deletes a product', function (): void {
    seedProduct('p1');

    $this->delete('/admin/products/p1')->assertRedirect(route('admin.products.index'));

    expect(rtdb()->get('products/p1'))->toBeNull();
});
