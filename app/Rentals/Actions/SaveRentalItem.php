<?php

declare(strict_types=1);

namespace App\Rentals\Actions;

use App\Cache\AvailabilityCache;
use App\Rentals\RentalInput;
use App\Rtdb\Contracts\RtdbClient;
use App\Support\Image\ImageCompressor;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Creates or edits a rental item (PLAN §4 *Admin* — Inventory).
 *
 * Inventory is rental units only: `name · image · price/day · units`. The
 * uploaded image is resized server-side and stored as base64 — no raw upload
 * ever reaches RTDB, reusing #21's {@see ImageCompressor}. `units` is the
 * on-hand count; an edit that changes the name or price keeps the current
 * on-hand units unless the form supplies a new figure (the stepper and Add stock
 * own the count day to day).
 *
 * Every write evicts the shared availability cache so the public catalog
 * reflects the item on the next request.
 */
final readonly class SaveRentalItem
{
    public function __construct(
        private RtdbClient $client,
        private ImageCompressor $images,
        private AvailabilityCache $cache,
    ) {}

    public function __invoke(?string $id, RentalInput $input): string
    {
        $name = trim($input->name);

        if ($name === '') {
            throw new InvalidArgumentException('A rental item needs a name.');
        }

        if ($input->pricePerDay <= 0) {
            throw new InvalidArgumentException('A rental item needs a price per day.');
        }

        $existing = null;

        if ($id !== null) {
            $stored = $this->client->get("rentals/{$id}");

            if (! is_array($stored)) {
                throw new InvalidArgumentException("No rental item with id [{$id}].");
            }

            $existing = $stored;
        }

        $id ??= (string) Str::ulid();

        $node = [
            'name' => $name,
            'imageBase64' => $input->image !== null
                ? $this->images->compressToBase64($this->readImage($input))
                : $this->existingImage($existing),
            'pricePerDay' => round($input->pricePerDay, 2),
            'units' => max(0, $input->units),
            'createdAt' => is_array($existing) && is_string($existing['createdAt'] ?? null)
                ? $existing['createdAt']
                : now('UTC')->toIso8601ZuluString(),
        ];

        // Preserve the receiving history across an edit.
        if (is_array($existing) && is_array($existing['stock'] ?? null)) {
            $node['stock'] = $existing['stock'];
        }

        $this->client->set("rentals/{$id}", $node);

        $this->cache->forgetRentals();

        return $id;
    }

    /**
     * @param  array<mixed, mixed>|null  $existing
     */
    private function existingImage(?array $existing): string
    {
        $image = $existing['imageBase64'] ?? '';

        return is_string($image) ? $image : '';
    }

    private function readImage(RentalInput $input): string
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
