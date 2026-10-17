<?php

declare(strict_types=1);

namespace App\Cache;

use App\Rtdb\Contracts\RtdbClient;

/**
 * The `catalog` cache entry — the coalesced read behind the customer menu and
 * product pages (PLAN §4 *Quota discipline*, rule 2).
 *
 * Product, category, and settings writes evict it, so a price, promo,
 * visibility, category, add-on, or image change propagates on the next request
 * rather than after the TTL backstop. The settings slice (#20) shares this
 * eviction point, so an emergency force-close reaches the menu on the next
 * request too.
 *
 * Products and categories are two RTDB nodes, so the single logical catalog
 * entry is stored under two keys and evicted together: the public site reads
 * both through `RtdbClient::cached`, and one page view never re-reads the
 * base64 product images. `stats/top3` is its own entry (rule 2) — a sale never
 * busts the menu — so its key is declared here but never forgotten by a
 * catalog write.
 */
final readonly class CatalogCache
{
    /** The products node — the entry whose rows carry the base64 images. */
    public const KEY = 'catalog';

    /** The categories node, evicted alongside the products entry. */
    public const CATEGORIES_KEY = 'catalog:categories';

    /** The ranking node (#31), its own entry — never busted by a sale. */
    public const TOP3_KEY = 'top3';

    /**
     * The TTL backstop for the nobody-edited case; writes are the propagation
     * path, so this only bounds reads no write has touched.
     */
    public const TTL_SECONDS = 300;

    public function __construct(private RtdbClient $client) {}

    /**
     * Evict the catalog entry so the next menu read re-reads RTDB.
     */
    public function forget(): void
    {
        $this->client->forget(self::KEY);
        $this->client->forget(self::CATEGORIES_KEY);
    }
}
