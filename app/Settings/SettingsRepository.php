<?php

declare(strict_types=1);

namespace App\Settings;

use App\Cache\CatalogCache;
use App\Rtdb\Contracts\RtdbClient;

/**
 * The single read/write seam for the `settings` node.
 *
 * Reads are served through `RtdbClient::cached` behind a short TTL — a backstop
 * only. The propagation path is eviction: every write forgets both the settings
 * entry and the shared **catalog** entry, so a force-close (or any hours,
 * closure, or banner edit) reaches the customer site on the next request
 * rather than after a TTL (§4 *Quota discipline*, rule 2).
 */
final readonly class SettingsRepository
{
    private const CACHE_KEY = 'settings';

    public function __construct(
        private RtdbClient $client,
        private CatalogCache $catalog,
    ) {}

    public function current(): Settings
    {
        $node = $this->client->cached(self::CACHE_KEY, 'settings', self::ttl());

        return is_array($node) ? Settings::fromNode($node) : Settings::defaults();
    }

    /**
     * Persist the whole settings node and evict both the settings read and the
     * catalog cache so the change propagates on the next request.
     */
    public function save(Settings $settings): Settings
    {
        $this->client->set('settings', $settings->toNode());

        $this->evict();

        return $settings;
    }

    /**
     * Evict the settings read and the shared catalog cache.
     */
    public function evict(): void
    {
        $this->client->forget(self::CACHE_KEY);
        $this->catalog->forget();
    }

    private static function ttl(): int
    {
        $ttl = config('settings.cache_ttl');

        return is_numeric($ttl) ? (int) $ttl : 120;
    }
}
