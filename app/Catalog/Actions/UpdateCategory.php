<?php

declare(strict_types=1);

namespace App\Catalog\Actions;

use App\Cache\CatalogCache;
use App\Rtdb\Contracts\RtdbClient;
use InvalidArgumentException;

/**
 * Renames and reorders an existing category.
 */
final readonly class UpdateCategory
{
    public function __construct(
        private RtdbClient $client,
        private CatalogCache $catalog,
    ) {}

    public function __invoke(string $id, string $name, int $order): void
    {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException('A category needs a name.');
        }

        if (! is_array($this->client->get("categories/{$id}"))) {
            throw new InvalidArgumentException("No category with id [{$id}].");
        }

        $this->client->update("categories/{$id}", ['name' => $name, 'order' => $order]);

        $this->catalog->forget();
    }
}
