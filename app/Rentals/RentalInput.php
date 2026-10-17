<?php

declare(strict_types=1);

namespace App\Rentals;

use Illuminate\Http\UploadedFile;

/**
 * The admin's rental-item form input, already validated at the request boundary.
 *
 * Bundles the fields so {@see \App\Rentals\Actions\SaveRentalItem}'s signature
 * stays small and typed, and carries the raw image upload so the action — not
 * the controller — owns compression and storage.
 */
final readonly class RentalInput
{
    public function __construct(
        public string $name,
        public float $pricePerDay,
        public int $units,
        public ?UploadedFile $image = null,
    ) {}
}
