<?php

declare(strict_types=1);

namespace App\Customer;

use App\Cache\CatalogCache;
use App\Rtdb\Contracts\RtdbClient;

/**
 * Reads the **Top 3** ranking node at `stats/top3` for the product-card chip.
 *
 * The ranking itself is #31 — this reader only renders what #31 writes, and
 * degrades gracefully: a missing or malformed node yields no tag rather than a
 * half-built chip. It is read from its own coalesced cache entry (PLAN §4
 * *Quota discipline*, rule 2), never per product, and a sale never busts it.
 *
 * The node is accepted in either shape a ranking writer may emit — a flat list
 * of product ids, or a list of `{productId}` rows — so this reader does not
 * couple #22 to a shape #31 has not yet fixed.
 */
final readonly class TopThree
{
    public function __construct(private RtdbClient $client) {}

    /**
     * The ranked product ids, at most three, in rank order.
     *
     * @return list<string>
     */
    public function productIds(): array
    {
        $node = $this->client->cached(
            CatalogCache::TOP3_KEY,
            'stats/top3',
            CatalogCache::TTL_SECONDS,
        );

        if (! is_array($node)) {
            return [];
        }

        $ids = [];

        // A named list of ranked rows (or a flat list of product ids), or a
        // map of productId => rank/true — accept whichever shape #31 writes.
        // `RtdbClient` stamps `updatedAt` on the node, so a whole-node fallback
        // must skip the watermark rather than read it as a product id.
        $reserved = ['updatedAt'];
        $raw = $node['rankings'] ?? $node['productIds'] ?? $node;

        if (is_array($raw)) {
            foreach ($raw as $key => $entry) {
                if (is_string($entry) && $entry !== '') {
                    $ids[] = $entry;

                    continue;
                }

                if (is_array($entry)) {
                    $id = $entry['productId'] ?? null;

                    if (is_string($id) && $id !== '') {
                        $ids[] = $id;
                    }

                    continue;
                }

                if (is_string($key) && $key !== '' && ! in_array($key, $reserved, true)
                    && $entry !== null && $entry !== false) {
                    $ids[] = $key;
                }
            }
        }

        return array_slice(array_values(array_unique($ids)), 0, 3);
    }
}
