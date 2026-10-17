<?php

declare(strict_types=1);

namespace App\Stats;

use App\Orders\OrderType;
use App\Rtdb\Contracts\RtdbClient;
use App\Rtdb\RtdbQuery;

/**
 * Reads the `sales/{id}` ledger — the revenue set the dashboard, reports, and
 * export all read (PLAN §4 *Admin*).
 *
 * Every read is **bounded by the indexed `soldAt` field** (PLAN §4 *Quota
 * discipline*, rule 3): a report or trend queries a date range, never the whole
 * tree. The ledger holds one row per paid order; a **rental booking** (#34)
 * writes a different sale type and is excluded here, so product rankings and the
 * product trend rank products only.
 */
final readonly class SalesLedger
{
    public function __construct(private RtdbClient $client) {}

    /**
     * Order sales sold in the UTC instant range [$fromIso, $toIso] inclusive,
     * keyed by sale id, rental bookings excluded.
     *
     * @return array<string, array<string, mixed>>
     */
    public function between(string $fromIso, string $toIso): array
    {
        $rows = $this->client->query('sales', RtdbQuery::orderBy('soldAt')->range($fromIso, $toIso));

        return array_filter($rows, static fn(array $row): bool => self::isOrderSale($row));
    }

    /**
     * Whether a sale row belongs to the revenue set — an order sale, never a
     * rental booking.
     *
     * @param  array<string, mixed>  $sale
     */
    public static function isOrderSale(array $sale): bool
    {
        $type = $sale['type'] ?? null;

        return is_string($type) && OrderType::tryFrom($type) !== null;
    }
}
