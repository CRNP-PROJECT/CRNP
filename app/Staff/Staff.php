<?php

declare(strict_types=1);

namespace App\Staff;

/**
 * A staff row read from `staff/{uid}` — name, Google email, and role.
 *
 * The row is the source of truth for access: a node whose role is missing or
 * unrecognised yields `null` (via `fromNode`), which the role guard treats as
 * a refusal rather than defaulting to a role.
 */
final readonly class Staff
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public StaffRole $role,
    ) {}

    /**
     * @param  array<mixed, mixed>  $node
     */
    public static function fromNode(string $id, array $node): ?self
    {
        $email = $node['email'] ?? null;
        $role = $node['role'] ?? null;
        $name = $node['name'] ?? null;

        if (! is_string($email) || $email === '' || ! is_string($role)) {
            return null;
        }

        $staffRole = StaffRole::tryFrom($role);

        if ($staffRole === null) {
            return null;
        }

        return new self(
            id: $id,
            name: is_string($name) && $name !== '' ? $name : $email,
            email: $email,
            role: $staffRole,
        );
    }
}
