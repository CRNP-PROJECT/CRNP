<?php

declare(strict_types=1);

namespace App\Rentals;

use App\Cache\AvailabilityCache;
use Carbon\CarbonInterface;

/**
 * The public **rental catalog**: each rentable item with its price per day and
 * its computed per-date availability (CONTEXT — *Rental booking*; PLAN §4
 * *Rentals*).
 *
 * The catalog is read-only and books nothing — the booking form lives on the
 * item page. Its computed result is served from the **shared availability
 * cache** (#33), so a burst of visitors costs one computation per TTL; booking,
 * stock, and item writes evict that entry so a reservation propagates on the
 * next request. The window is bounded by the `dateFrom` index (rule 3).
 */
final readonly class RentalCatalog
{
    public function __construct(
        private Rentals $items,
        private RentalAvailability $availability,
        private AvailabilityCache $cache,
    ) {}

    /**
     * The catalog for the window starting at $from, served from the coalesced
     * availability entry.
     *
     * @return list<RentalCatalogEntry>
     */
    public function entries(CarbonInterface $from): array
    {
        /** @var list<RentalCatalogEntry> $entries */
        $entries = $this->cache->rememberRentals(
            fn(): array => $this->compute($from),
        );

        return $entries;
    }

    /**
     * The catalog for one item, or null when the item does not exist.
     */
    public function entry(string $itemId, CarbonInterface $from): ?RentalCatalogEntry
    {
        foreach ($this->entries($from) as $entry) {
            if ($entry->item->id === $itemId) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * @return list<RentalCatalogEntry>
     */
    private function compute(CarbonInterface $from): array
    {
        $days = self::windowDays();

        // Read the overlapping bookings once for the whole window and share the
        // set across items, so the catalog costs one query rather than one per
        // item (rule 3).
        $overlapping = $this->availability->overlapping($from, $days);

        $entries = [];

        foreach ($this->items->all() as $item) {
            $entries[] = new RentalCatalogEntry(
                item: $item,
                availability: $this->availability->forItem($item, $from, $days, $overlapping),
            );
        }

        return $entries;
    }

    private static function windowDays(): int
    {
        $days = config('rentals.catalog_days');

        return is_numeric($days) && (int) $days > 0 ? (int) $days : 7;
    }
}
