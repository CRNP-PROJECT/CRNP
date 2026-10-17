<?php

declare(strict_types=1);

namespace App\Customer;

use App\Cache\CatalogCache;
use App\Catalog\Catalog;
use App\Catalog\Category;
use App\Catalog\Product;
use App\Rtdb\Contracts\RtdbClient;

/**
 * Reads the public menu — products and categories — from the coalesced
 * **catalog** cache entry (PLAN §4 *Quota discipline*, rule 2).
 *
 * The product node carries the base64 images, so a menu or product page must
 * never re-read it per view: both call {@see products()}, which serves the same
 * cache entry the admin writes evict. A page view costs at most one RTDB read
 * of `products` and one of `categories` per TTL window, no matter how many
 * customers view it.
 */
final readonly class Storefront
{
    public function __construct(private RtdbClient $client) {}

    /**
     * Every product (including hidden ones), keyed by id, from the cached read.
     *
     * @return array<string, Product>
     */
    public function products(): array
    {
        $node = $this->client->cached(CatalogCache::KEY, 'products', CatalogCache::TTL_SECONDS);

        return Catalog::productsFromNode($node);
    }

    /**
     * The menu set: products the admin has not hidden (the 86 board).
     *
     * @return array<string, Product>
     */
    public function menu(): array
    {
        return array_filter($this->products(), static fn(Product $product): bool => ! $product->hidden);
    }

    /**
     * Categories in the admin's order, keyed by id, from the cached read.
     *
     * @return array<string, Category>
     */
    public function categories(): array
    {
        $node = $this->client->cached(CatalogCache::CATEGORIES_KEY, 'categories', CatalogCache::TTL_SECONDS);

        return Catalog::categoriesFromNode($node);
    }

    /**
     * A single menu product, or null when it does not exist or is hidden.
     */
    public function product(string $id): ?Product
    {
        return $this->menu()[$id] ?? null;
    }

    /**
     * The menu grouped under category titles in the admin's category order.
     *
     * With $categoryId set, only that category's group is returned — the chip
     * filter. A product whose category was deleted (or never set) falls under a
     * trailing "All Products" group so it is never silently dropped from the
     * menu (PLAN §4 *Customer site* names that default title).
     *
     * @return list<MenuGroup>
     */
    public function groups(?string $categoryId = null): array
    {
        $menu = $this->menu();
        $categories = $this->categories();
        $groups = [];

        foreach ($categories as $id => $category) {
            if ($categoryId !== null && $categoryId !== $id) {
                continue;
            }

            $products = array_values(array_filter(
                $menu,
                static fn(Product $product): bool => $product->categoryId === $id,
            ));

            if ($products !== []) {
                $groups[] = new MenuGroup($id, $category->name, $products);
            }
        }

        if ($categoryId === null) {
            $known = array_keys($categories);
            $uncategorized = array_values(array_filter(
                $menu,
                static fn(Product $product): bool => ! in_array($product->categoryId, $known, true),
            ));

            if ($uncategorized !== []) {
                $groups[] = new MenuGroup('all', 'All Products', $uncategorized);
            }
        }

        return $groups;
    }
}
