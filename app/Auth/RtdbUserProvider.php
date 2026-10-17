<?php

declare(strict_types=1);

namespace App\Auth;

use App\Rtdb\Contracts\RtdbClient;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;

/**
 * The RTDB-backed user provider: the session guard's bridge to `users/{uid}`.
 *
 * Laravel's session guard remembers a signed-in customer by identifier; this
 * provider turns that identifier (the Google `sub`) back into an `RtdbUser`
 * by reading the node through `RtdbClient`, so the whole system keeps RTDB as
 * its only datastore with no Eloquent or SQL.
 */
final class RtdbUserProvider implements UserProvider
{
    public function __construct(private readonly RtdbClient $client) {}

    #[\Override]
    public function retrieveById($identifier): ?Authenticatable
    {
        if (! is_string($identifier) || $identifier === '') {
            return null;
        }

        $node = $this->client->get("users/{$identifier}");

        return is_array($node) ? RtdbUser::fromNode($identifier, $node) : null;
    }

    /**
     * Google sign-in carries no password or remember-me token, so these two
     * lookups never resolve a user.
     */
    #[\Override]
    public function retrieveByToken($identifier, #[\SensitiveParameter] $token): ?Authenticatable
    {
        return null;
    }

    #[\Override]
    public function updateRememberToken(Authenticatable $user, #[\SensitiveParameter] $token): void
    {
        // Sessions remember the customer; there is no token to persist.
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    #[\Override]
    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials): ?Authenticatable
    {
        return null;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    #[\Override]
    public function validateCredentials(Authenticatable $user, #[\SensitiveParameter] array $credentials): bool
    {
        return false;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    #[\Override]
    public function rehashPasswordIfRequired(Authenticatable $user, #[\SensitiveParameter] array $credentials, bool $force = false): void
    {
        // No passwords exist in this system.
    }
}
