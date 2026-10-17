<?php

declare(strict_types=1);

namespace App\Catalog\Actions;

use App\Cache\CatalogCache;
use App\Rtdb\Contracts\RtdbClient;
use InvalidArgumentException;

/**
 * Removes a product's promo in one click, returning it to its base price.
 *
 * RTDB has no null-child delete through PATCH, so the three promo fields are
 * removed by reading the node, unsetting them, and replacing it.
 */
final readonly class RemoveProductPromo
{
    public function __construct(
        private RtdbClient $client,
        private CatalogCache $catalog,
    ) {}

    public function __invoke(string $id): void
    {
        $node = $this->client->get("products/{$id}");

        if (! is_array($node)) {
            throw new InvalidArgumentException("No product with id [{$id}].");
        }

        unset($node['promoMode'], $node['promoValue'], $node['promoPrice']);

        $this->client->set("products/{$id}", $this->stringKeyed($node));

        $this->catalog->forget();
    }

    /**
     * @param  array<mixed, mixed>  $node
     * @return array<string, mixed>
     */
    private function stringKeyed(array $node): array
    {
        $normalized = [];

        foreach ($node as $key => $value) {
            $normalized[(string) $key] = $value;
        }

        return $normalized;
    }
}
