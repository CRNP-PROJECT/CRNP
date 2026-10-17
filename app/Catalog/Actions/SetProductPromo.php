<?php

declare(strict_types=1);

namespace App\Catalog\Actions;

use App\Cache\CatalogCache;
use App\Catalog\Promo;
use App\Catalog\PromoMode;
use App\Rtdb\Contracts\RtdbClient;
use InvalidArgumentException;

/**
 * The one-click **Add promo**: given the segmented mode + one number, it derives
 * the sibling value and the "% off" label against the product's base price and
 * stores `promoMode` + `promoValue` + `promoPrice`.
 *
 * A promo is validated `0 < promoPrice < price`; the product form stays
 * promo-free, so this is the only path that writes a promo. The write evicts the
 * catalog cache.
 */
final readonly class SetProductPromo
{
    public function __construct(
        private RtdbClient $client,
        private CatalogCache $catalog,
    ) {}

    public function __invoke(string $id, PromoMode $mode, float $value): Promo
    {
        $node = $this->client->get("products/{$id}");
        $price = is_array($node) ? ($node['price'] ?? null) : null;

        if (! is_numeric($price)) {
            throw new InvalidArgumentException("No product with id [{$id}].");
        }

        // Throws on an out-of-range promo, so an invalid one is never stored.
        $promo = Promo::derive($mode, $value, (float) $price);

        $this->client->update("products/{$id}", [
            'promoMode' => $promo->mode->value,
            'promoValue' => $promo->value,
            'promoPrice' => $promo->promoPrice,
        ]);

        $this->catalog->forget();

        return $promo;
    }
}
