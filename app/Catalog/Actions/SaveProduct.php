<?php

declare(strict_types=1);

namespace App\Catalog\Actions;

use App\Cache\CatalogCache;
use App\Catalog\AddOn;
use App\Catalog\Product;
use App\Catalog\ProductInput;
use App\Catalog\Promo;
use App\Catalog\PromoMode;
use App\Rtdb\Contracts\RtdbClient;
use App\Support\Image\ImageCompressor;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Creates or edits a product.
 *
 * The uploaded image is resized server-side and stored as base64 — no raw file
 * upload ever reaches RTDB. A base-price edit re-derives any existing promo
 * against the new price; a promo the new price can no longer honour is dropped
 * rather than stored invalid. Every write evicts the catalog cache.
 */
final readonly class SaveProduct
{
    public function __construct(
        private RtdbClient $client,
        private ImageCompressor $images,
        private CatalogCache $catalog,
    ) {}

    public function __invoke(?string $id, ProductInput $input): string
    {
        $name = trim($input->name);

        if ($name === '') {
            throw new InvalidArgumentException('A product needs a name.');
        }

        if (! is_array($this->client->get("categories/{$input->categoryId}"))) {
            throw new InvalidArgumentException("No category with id [{$input->categoryId}].");
        }

        $existing = null;

        if ($id !== null) {
            $stored = $this->client->get("products/{$id}");

            if (! is_array($stored)) {
                throw new InvalidArgumentException("No product with id [{$id}].");
            }

            $existing = $stored;
        }

        $id ??= (string) Str::ulid();

        $node = [
            'name' => $name,
            'description' => trim($input->description),
            'price' => round($input->price, 2),
            'categoryId' => $input->categoryId,
            'imageBase64' => $input->image !== null
                ? $this->images->compressToBase64($this->readImage($input))
                : $this->existingImage($existing),
            'hidden' => is_array($existing) ? ($existing['hidden'] ?? false) === true : false,
            'addOns' => array_map(
                static fn(AddOn $addOn): array => $addOn->toNode(),
                $input->addOns,
            ),
            'createdAt' => is_array($existing) && is_string($existing['createdAt'] ?? null)
                ? $existing['createdAt']
                : now('UTC')->toIso8601ZuluString(),
        ];

        if ($input->theme !== null && $input->theme !== '') {
            $node['theme'] = $input->theme;
        } elseif (is_array($existing) && isset($existing['theme']) && is_string($existing['theme']) && $existing['theme'] !== '') {
            $node['theme'] = $existing['theme'];
        }

        $promo = $this->rederivePromo($existing, $node['price']);

        if ($promo !== null) {
            $node += [
                'promoMode' => $promo->mode->value,
                'promoValue' => $promo->value,
                'promoPrice' => $promo->promoPrice,
            ];
        }

        $this->client->set("products/{$id}", $node);

        $this->catalog->forget();

        return $id;
    }

    /**
     * Re-derive a stored promo against a (possibly new) base price, or null
     * when the product has no promo or the new price cannot honour it.
     *
     * @param  array<mixed, mixed>|null  $existing
     */
    private function rederivePromo(?array $existing, float $price): ?Promo
    {
        if ($existing === null) {
            return null;
        }

        $mode = $existing['promoMode'] ?? null;
        $value = $existing['promoValue'] ?? null;

        if (! is_string($mode) || ! is_numeric($value)) {
            return null;
        }

        $promoMode = PromoMode::tryFrom($mode);

        if ($promoMode === null) {
            return null;
        }

        try {
            return Promo::derive($promoMode, (float) $value, $price);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * @param  array<mixed, mixed>|null  $existing
     */
    private function existingImage(?array $existing): string
    {
        $image = $existing['imageBase64'] ?? '';

        return is_string($image) ? $image : '';
    }

    private function readImage(ProductInput $input): string
    {
        $path = $input->image?->getRealPath();

        if ($path === false || $path === null) {
            throw new InvalidArgumentException('The uploaded image could not be read.');
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new InvalidArgumentException('The uploaded image could not be read.');
        }

        return $contents;
    }
}
