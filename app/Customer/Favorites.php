<?php

declare(strict_types=1);

namespace App\Customer;

use App\Auth\RtdbUser;
use App\Rtdb\Contracts\RtdbClient;
use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;

/**
 * The per-account favorites on `users/{uid}/favorites/{productId}` (PLAN §4
 * *Customer site* — favorites sync per account across devices).
 *
 * Reads and writes go through `RtdbClient`, so a favorite set on one device is
 * visible on the next. There is no favorites page or filter in v1: hearts
 * persist and sync, nothing more. The set and unset are single-child merges, so
 * `favorites/{productId}` is exactly `true` and unsetting removes the key.
 */
final readonly class Favorites
{
    public function __construct(private RtdbClient $client) {}

    /**
     * The product ids this account has favorited.
     *
     * @return list<string>
     */
    public function forUser(?Authenticatable $user): array
    {
        if (! $user instanceof RtdbUser) {
            return [];
        }

        $node = $this->client->get("users/{$user->sub}/favorites");

        if (! is_array($node)) {
            return [];
        }

        $ids = [];

        foreach ($node as $id => $value) {
            if (is_string($id) && $value === true) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function isFavorite(?Authenticatable $user, string $productId): bool
    {
        return in_array($productId, $this->forUser($user), true);
    }

    /**
     * Toggle a favorite and return its new state.
     *
     * Setting merges `{productId: true}`; unsetting deletes the key rather than
     * storing `false`, so the node holds only real favorites.
     */
    public function toggle(RtdbUser $user, string $productId): bool
    {
        if (trim($productId) === '') {
            throw new InvalidArgumentException('A favorite needs a product id.');
        }

        if ($this->isFavorite($user, $productId)) {
            $this->client->delete("users/{$user->sub}/favorites/{$productId}");

            return false;
        }

        $this->client->update("users/{$user->sub}/favorites", [$productId => true]);

        return true;
    }
}
