<?php

declare(strict_types=1);

namespace App\Customer;

use App\Catalog\Product;

/**
 * One category's slice of the public menu — the products under a category
 * title, in the admin's category order.
 */
final readonly class MenuGroup
{
    /**
     * @param  list<Product>  $products
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $products,
    ) {}
}
