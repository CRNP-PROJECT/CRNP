<?php

declare(strict_types=1);

namespace App\Activity;

/**
 * One row of the admin **Activity** feed: *time · who · action · order code*
 * (CONTEXT — *Activity*).
 *
 * It is assembled from the audit records (#29 Restore) and the `handledBy`
 * stamps on the sales ledger, so the same shape serves both sources.
 */
final readonly class ActivityEntry
{
    public function __construct(
        public string $at,
        public string $who,
        public string $action,
        public string $code,
    ) {}

    /**
     * The action label for a sale row, keyed off the order origin.
     */
    public static function saleAction(string $type): string
    {
        return $type === 'online' ? 'Mark served' : 'Settled walk-in';
    }

    /**
     * A human label for an audit action slug.
     */
    public static function auditAction(string $action): string
    {
        return match ($action) {
            'restore' => 'Restore',
            default => ucfirst(str_replace('_', ' ', $action)),
        };
    }
}
