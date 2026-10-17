<?php

declare(strict_types=1);

namespace App\Catalog\Actions;

use App\Cache\CatalogCache;
use App\Rtdb\Contracts\RtdbClient;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Creates a menu category from an admin-supplied name and sort order.
 *
 * The id is a ULID the app owns (RTDB-safe), and the write evicts the catalog
 * cache so the menu re-reads categories on the next request.
 */
final readonly class CreateCategory
{
    public function __construct(
        private RtdbClient $client,
        private CatalogCache $catalog,
    ) {}

    public function __invoke(string $name, int $order): string
    {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException('A category needs a name.');
        }

        $id = (string) Str::ulid();

        $this->client->set("categories/{$id}", ['name' => $name, 'order' => $order]);

        $this->catalog->forget();

        return $id;
    }
}
