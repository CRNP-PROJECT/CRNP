<?php

declare(strict_types=1);

namespace App\Rentals\Actions;

use App\Cache\AvailabilityCache;
use App\Rtdb\Contracts\RtdbClient;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Records a **receiving entry** that raises rental stock (CONTEXT — *Restock*;
 * PLAN §4 *Admin* — Inventory).
 *
 * Add stock is the only path that adds rental units by purchase: supplier ·
 * quantity · date (three inputs, the quantity a stepper, the date defaulting
 * today). The units rise by the quantity and the entry is appended under
 * `rentals/{id}/stock` so the receiving history is kept. Rentals returned from
 * hire rise through **Confirm return**, never here.
 *
 * The write evicts the shared availability cache so the catalog reflects the
 * raised stock on the next request.
 */
final readonly class AddRentalStock
{
    public function __construct(
        private RtdbClient $client,
        private AvailabilityCache $cache,
    ) {}

    public function __invoke(string $id, string $supplier, int $qty, string $date): void
    {
        $node = $this->client->get("rentals/{$id}");

        if (! is_array($node)) {
            throw new InvalidArgumentException("No rental item with id [{$id}].");
        }

        if ($qty < 1) {
            throw new InvalidArgumentException('Add stock needs a quantity of at least 1.');
        }

        $units = is_numeric($node['units'] ?? null) ? (int) $node['units'] : 0;
        $entryId = (string) Str::ulid();

        $this->client->update("rentals/{$id}", ['units' => $units + $qty]);
        $this->client->set("rentals/{$id}/stock/{$entryId}", [
            'supplier' => trim($supplier),
            'qty' => $qty,
            'date' => $date,
            'receivedAt' => now('UTC')->toIso8601ZuluString(),
        ]);

        $this->cache->forgetRentals();
    }
}
