<?php

declare(strict_types=1);

namespace App\Auth;

/**
 * The three sign-in areas the app keeps separate.
 *
 * Each area maps to its own auth guard, so the same browser can be signed in as
 * a customer, an admin, and a cashier at once without one login evicting
 * another. An area owns the guard name, the prompt it lands a guest on, the
 * screen it hands back after sign-in, and where its sign-out returns to.
 */
enum AuthArea: string
{
    case Customer = 'web';
    case Admin = 'admin';
    case Cashier = 'cashier';

    /**
     * The auth guard this area reads and writes.
     */
    public function guard(): string
    {
        return $this->value;
    }

    /**
     * The sign-in prompt a guest is sent to for this area.
     */
    public function loginRoute(): string
    {
        return match ($this) {
            self::Customer => 'login',
            self::Admin => 'admin.login',
            self::Cashier => 'cashier.login',
        };
    }

    /**
     * The screen this area lands on after a successful sign-in.
     */
    public function homeRoute(): string
    {
        return match ($this) {
            self::Customer => 'home',
            self::Admin => 'admin.dashboard',
            self::Cashier => 'cashier.register',
        };
    }

    /**
     * Where this area's sign-out returns to.
     */
    public function logoutRoute(): string
    {
        return match ($this) {
            self::Customer => 'home',
            self::Admin => 'admin.login',
            self::Cashier => 'cashier.login',
        };
    }

    /**
     * Resolve an area from a stored value, defaulting to the customer area so
     * a missing or malformed hint can never widen access.
     */
    public static function fromStored(mixed $value): self
    {
        return is_string($value) ? (self::tryFrom($value) ?? self::Customer) : self::Customer;
    }
}
