<?php

declare(strict_types=1);

namespace App\Auth;

/**
 * The profile fields a Google sign-in yields, decoupled from Socialite so the
 * provisioning rule can be exercised without the OAuth client.
 */
final readonly class GoogleIdentity
{
    public function __construct(
        public string $sub,
        public ?string $email,
        public ?string $name,
        public ?string $avatar,
    ) {}
}
