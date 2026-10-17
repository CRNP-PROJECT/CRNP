<?php

declare(strict_types=1);

namespace App\Settings;

/**
 * The computed open/closed state at a point in time.
 *
 * `label` carries the scheduled closure's admin-written name (e.g. "Christmas")
 * so the badge can say why the site is shut; it is null for every other reason.
 */
final readonly class StoreState
{
    public function __construct(
        public bool $isOpen,
        public ?ClosedReason $reason = null,
        public ?string $label = null,
    ) {}

    public static function open(): self
    {
        return new self(isOpen: true);
    }

    public static function closed(ClosedReason $reason, ?string $label = null): self
    {
        return new self(isOpen: false, reason: $reason, label: $label);
    }
}
