<?php

declare(strict_types=1);

namespace App\Catalog\Actions;

use App\Cache\CatalogCache;
use App\Rtdb\Contracts\RtdbClient;
use InvalidArgumentException;

/**
 * The 86 board: one tap hides a product from the menu, another shows it again.
 *
 * Menu items carry no stock, so this toggle — not a counter — is how a sold-out
 * item leaves the menu. The write evicts the catalog cache.
 */
final readonly class SetProductVisibility
{
    public function __construct(
        private RtdbClient $client,
        private CatalogCache $catalog,
    ) {}

    public function __invoke(string $id, bool $hidden): void
    {
        if (! is_array($this->client->get("products/{$id}"))) {
            throw new InvalidArgumentException("No product with id [{$id}].");
        }

        $this->client->update("products/{$id}", ['hidden' => $hidden]);

        $this->catalog->forget();
    }
}
