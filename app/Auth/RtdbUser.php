<?php

declare(strict_types=1);

namespace App\Auth;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * A customer identity read from `users/{uid}`, keyed by the Google `sub`.
 *
 * The system has no passwords: this object satisfies Laravel's
 * `Authenticatable` contract without ever carrying a credential, so the
 * session guard can remember a signed-in customer with no SQL user table.
 */
final class RtdbUser implements Authenticatable
{
    public function __construct(
        public readonly string $sub,
        public readonly ?string $email = null,
        public readonly ?string $name = null,
        public readonly ?string $avatar = null,
    ) {}

    /**
     * @param  array<mixed, mixed>  $node
     */
    public static function fromNode(string $sub, array $node): self
    {
        return new self(
            sub: $sub,
            email: self::stringOrNull($node['email'] ?? null),
            name: self::stringOrNull($node['name'] ?? null),
            avatar: self::stringOrNull($node['avatar'] ?? null),
        );
    }

    #[\Override]
    public function getAuthIdentifierName(): string
    {
        return 'sub';
    }

    #[\Override]
    public function getAuthIdentifier(): string
    {
        return $this->sub;
    }

    #[\Override]
    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    /**
     * There is no password anywhere in the system; the field is null so the
     * guard never stores or checks a credential.
     */
    #[\Override]
    public function getAuthPassword(): ?string
    {
        return null;
    }

    #[\Override]
    public function getRememberToken(): ?string
    {
        return null;
    }

    #[\Override]
    public function setRememberToken($value): void
    {
        // Sessions remember the customer; there is no remember-me token to set.
    }

    /**
     * An empty name tells the guard to skip remember-token bookkeeping.
     */
    #[\Override]
    public function getRememberTokenName(): string
    {
        return '';
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }
}
