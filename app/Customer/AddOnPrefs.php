<?php

declare(strict_types=1);

namespace App\Customer;

use App\Auth\RtdbUser;
use App\Rtdb\Contracts\RtdbClient;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The last add-on selection a customer made per product, at
 * `users/{uid}/addOnPrefs/{productId}` (PLAN §4 *Customer site* — the last
 * add-on selection is re-checked next visit, on any device).
 *
 * Stored as a map of add-on name → `true`, so `RtdbClient` can stamp its
 * `updatedAt` watermark without corrupting the selection. Saving is a single
 * child set; a customer who clears every add-on removes the preference rather
 * than storing an empty map.
 */
final readonly class AddOnPrefs
{
    public function __construct(private RtdbClient $client) {}

    /**
     * The add-on names to pre-check for this product, or an empty list.
     *
     * @return list<string>
     */
    public function forProduct(?Authenticatable $user, string $productId): array
    {
        if (! $user instanceof RtdbUser) {
            return [];
        }

        $node = $this->client->get("users/{$user->sub}/addOnPrefs/{$productId}");

        if (! is_array($node)) {
            return [];
        }

        $names = [];

        foreach ($node as $name => $selected) {
            if (is_string($name) && $selected === true) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * Save the chosen add-on names for a product; an empty selection clears it.
     *
     * @param  array<int, mixed>  $names
     */
    public function save(RtdbUser $user, string $productId, array $names): void
    {
        $selected = [];

        foreach ($names as $name) {
            $name = is_string($name) ? trim($name) : '';

            if ($name !== '') {
                $selected[$name] = true;
            }
        }

        if ($selected === []) {
            $this->client->delete("users/{$user->sub}/addOnPrefs/{$productId}");

            return;
        }

        $this->client->set("users/{$user->sub}/addOnPrefs/{$productId}", $selected);
    }
}
