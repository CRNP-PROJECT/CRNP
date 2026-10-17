<?php

declare(strict_types=1);

namespace App\Rentals\Actions;

use App\Cache\AvailabilityCache;
use App\Rtdb\Contracts\RtdbClient;
use InvalidArgumentException;

/**
 * Deletes a rental item and evicts the shared availability cache.
 */
final readonly class DeleteRentalItem
{
    public function __construct(
        private RtdbClient $client,
        private AvailabilityCache $cache,
    ) {}

    public function __invoke(string $id): void
    {
        if (! is_array($this->client->get("rentals/{$id}"))) {
            throw new InvalidArgumentException("No rental item with id [{$id}].");
        }

        $this->client->delete("rentals/{$id}");

        $this->cache->forgetRentals();
    }
}
