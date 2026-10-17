<?php

declare(strict_types=1);

namespace App\Catalog\Actions;

use App\Cache\CatalogCache;
use App\Catalog\Catalog;
use App\Rtdb\Contracts\RtdbClient;
use InvalidArgumentException;

/**
 * Deletes a category, refusing while any product still references it.
 *
 * A dangling `categoryId` would leave a product with no menu heading, so the
 * reference check runs here rather than letting the delete orphan a product.
 */
final readonly class DeleteCategory
{
    public function __construct(
        private RtdbClient $client,
        private Catalog $catalog,
        private CatalogCache $catalogCache,
    ) {}

    public function __invoke(string $id): void
    {
        if (! is_array($this->client->get("categories/{$id}"))) {
            throw new InvalidArgumentException("No category with id [{$id}].");
        }

        foreach ($this->catalog->products() as $product) {
            if ($product->categoryId === $id) {
                throw new InvalidArgumentException(
                    "The category [{$id}] still has products and cannot be deleted.",
                );
            }
        }

        $this->client->delete("categories/{$id}");

        $this->catalogCache->forget();
    }
}
