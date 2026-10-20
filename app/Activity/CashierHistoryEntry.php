<?php

declare(strict_types=1);

namespace App\Activity;

/**
 * One row of the admin **Cashier history** feed: *time · cashier · decision ·
 * subject · code · detail*.
 *
 * It is assembled from the same stamps the Activity feed reads — the `handledBy`
 * field on the sales ledger and the rejection record on an order — plus the
 * `handledBy` stamp a rental booking carries when the counter confirms,
 * hands over, returns, or cancels it. The shape serves all three sources.
 */
final readonly class CashierHistoryEntry
{
    public function __construct(
        public string $at,
        public string $who,
        public string $decision,
        public string $subject,
        public string $code,
        public string $detail,
    ) {}
}
