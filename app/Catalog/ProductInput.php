<?php

declare(strict_types=1);

namespace App\Catalog;

use Illuminate\Http\UploadedFile;

/**
 * The admin's product form input, already validated at the request boundary.
 *
 * Bundling the fields keeps `SaveProduct`'s signature small and its inputs
 * typed, and carries the raw image upload so the action — not the controller —
 * owns compression and storage.
 */
final readonly class ProductInput
{
    /**
     * @param  list<AddOn>  $addOns
     */
    public function __construct(
        public string $name,
        public string $description,
        public float $price,
        public string $categoryId,
        public array $addOns,
        public ?UploadedFile $image = null,
        public ?string $theme = null,
    ) {}
}
