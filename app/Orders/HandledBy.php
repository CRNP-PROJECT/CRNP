<?php

declare(strict_types=1);

namespace App\Orders;

use App\Staff\Staff;

/**
 * The cashier stamped on a money write — uid + name (PLAN §4 *Cashier POS*:
 * every walk-in creation, Mark served, Collect late, and sale row carries
 * `handledBy`).
 *
 * The uid is the stable staff node id, so admin Activity and per-cashier
 * totals can group on it without a second lookup; the name is snapshotted so a
 * later rename never rewrites history.
 */
final readonly class HandledBy
{
    public function __construct(
        public string $uid,
        public string $name,
    ) {}

    public static function fromStaff(Staff $staff): self
    {
        return new self($staff->id, $staff->name);
    }

    /**
     * @param  array<mixed, mixed>  $node
     */
    public static function fromNode(array $node): ?self
    {
        $uid = $node['uid'] ?? null;
        $name = $node['name'] ?? null;

        if (! is_string($uid) || $uid === '' || ! is_string($name)) {
            return null;
        }

        return new self($uid, $name);
    }

    /**
     * @return array{uid: string, name: string}
     */
    public function toNode(): array
    {
        return ['uid' => $this->uid, 'name' => $this->name];
    }
}
