<?php

declare(strict_types=1);

use App\Cache\CatalogCache;
use App\Catalog\Catalog;
use App\Staff\StaffRole;

beforeEach(function (): void {
    seedStaff('owner@example.com', StaffRole::Admin, 'Owner Santos');

    signIn('owner@example.com');
});

it('lists the admin-managed categories in the admin order', function (): void {
    seedCategory('mains', 'Mains', 2);
    seedCategory('drinks', 'Drinks', 1);

    $this->get('/admin/categories')
        ->assertOk()
        ->assertSee('Drinks')
        ->assertSee('Mains');
});

it('creates a category with a name and an order', function (): void {
    $this->post('/admin/categories', ['name' => 'Drinks', 'order' => '1'])
        ->assertRedirect(route('admin.categories.index'));

    $categories = rtdb()->get('categories');

    expect($categories)->toBeArray()->toHaveCount(1);

    $id = (string) array_key_first($categories);

    expect($categories[$id])->toMatchArray(['name' => 'Drinks', 'order' => 1]);
});

it('renames and reorders a category', function (): void {
    seedCategory('drinks', 'Drinks', 1);

    $this->put('/admin/categories/drinks', ['name' => 'Beverages', 'order' => '3'])
        ->assertRedirect(route('admin.categories.index'));

    expect(rtdb()->get('categories/drinks'))->toMatchArray(['name' => 'Beverages', 'order' => 3]);
});

it('deletes an unused category', function (): void {
    seedCategory('drinks', 'Drinks');

    $this->delete('/admin/categories/drinks')->assertRedirect(route('admin.categories.index'));

    expect(rtdb()->get('categories/drinks'))->toBeNull();
});

it('refuses to delete a category a product still references', function (): void {
    seedCategory('drinks', 'Drinks');
    rtdb()->seed('products/p1', ['name' => 'Milktea', 'price' => 120, 'categoryId' => 'drinks']);

    $this->delete('/admin/categories/drinks')->assertSessionHasErrors('category');

    expect(rtdb()->get('categories/drinks'))->not->toBeNull();
});

it('evicts the catalog cache when a category is written', function (): void {
    rtdb()->cached(CatalogCache::KEY, 'categories', 300);

    $this->post('/admin/categories', ['name' => 'Drinks', 'order' => '1']);

    expect(Cache::get(CatalogCache::KEY))->toBeNull();
});

it('orders the menu categories by the admin order', function (): void {
    seedCategory('mains', 'Mains', 2);
    seedCategory('drinks', 'Drinks', 1);

    $ordered = array_map(fn($category): string => $category->name, app(Catalog::class)->categories());

    expect(array_values($ordered))->toBe(['Drinks', 'Mains']);
});
