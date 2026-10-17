<?php

declare(strict_types=1);

namespace App\Catalog\Actions;

use App\Cache\CatalogCache;
use App\Rtdb\Contracts\RtdbClient;
use InvalidArgumentException;

/**
 * Deletes a product and evicts the catalog cache.
 */
final readonly class DeleteProduct
{
    public function __construct(
        private RtdbClient $client,
        private CatalogCache $catalog,
    ) {}

    public function __invoke(string $id): void
    {
        if (! is_array($this->client->get("products/{$id}"))) {
            throw new InvalidArgumentException("No product with id [{$id}].");
        }

        $this->client->delete("products/{$id}");

        $this->catalog->forget();
    }
}
