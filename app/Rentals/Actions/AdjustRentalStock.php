<?php

declare(strict_types=1);

namespace App\Rentals\Actions;

use App\Cache\AvailabilityCache;
use App\Rtdb\Contracts\RtdbClient;
use InvalidArgumentException;

/**
 * Adjusts an item's on-hand units by a signed delta — the inline `− / +`
 * stepper (PLAN §3, §4 *Admin* — Inventory).
 *
 * The stepper is the one-tap day-to-day adjustment; **Add stock** stays for
 * receiving purchases and **Confirm return** raises stock when rented units come
 * back. The count is floored at zero, so a stray `−` can never drive it
 * negative. The write evicts the shared availability cache.
 */
final readonly class AdjustRentalStock
{
    public function __construct(
        private RtdbClient $client,
        private AvailabilityCache $cache,
    ) {}

    public function __invoke(string $id, int $delta): int
    {
        $node = $this->client->get("rentals/{$id}");

        if (! is_array($node)) {
            throw new InvalidArgumentException("No rental item with id [{$id}].");
        }

        $units = is_numeric($node['units'] ?? null) ? (int) $node['units'] : 0;
        $updated = max(0, $units + $delta);

        $this->client->update("rentals/{$id}", ['units' => $updated]);

        $this->cache->forgetRentals();

        return $updated;
    }
}
