<?php

declare(strict_types=1);

namespace App\Stats;

use App\Orders\LineItem;

/**
 * Tallies a set of `sales/{id}` rows into per-product units and revenue
 * (PLAN §4 *Admin* — best-sellers).
 *
 * The dashboard's best-sellers are computed server-side from the sales ledger,
 * ranked by units sold with ties broken by revenue then name — the same rule the
 * Top 3 chip uses (#31). Rental bookings never reach here: the caller passes the
 * order-sale set only ({@see SalesLedger::between()}).
 */
final readonly class ProductTally
{
    private function __construct() {}

    /**
     * The ranked products, highest first, capped at $limit.
     *
     * @param  list<array<string, mixed>>  $sales
     * @return list<array{productId: string, name: string, units: int, revenue: float}>
     */
    public static function rank(array $sales, int $limit): array
    {
        /** @var array<string, array{name: string, units: int, revenue: float}> $tally */
        $tally = [];

        foreach ($sales as $sale) {
            $lines = is_array($sale['lines'] ?? null) ? $sale['lines'] : [];

            foreach ($lines as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $line = LineItem::fromNode($row);

                if ($line === null) {
                    continue;
                }

                $current = $tally[$line->productId] ?? ['name' => $line->name, 'units' => 0, 'revenue' => 0.0];

                $tally[$line->productId] = [
                    'name' => $current['name'],
                    'units' => $current['units'] + $line->quantity,
                    'revenue' => round($current['revenue'] + $line->lineTotal(), 2),
                ];
            }
        }

        uasort($tally, static function (array $a, array $b): int {
            return $b['units'] <=> $a['units']
                ?: $b['revenue'] <=> $a['revenue']
                ?: strcmp($a['name'], $b['name']);
        });

        $ranked = [];

        foreach (array_slice($tally, 0, $limit, true) as $productId => $row) {
            $ranked[] = [
                'productId' => $productId,
                'name' => $row['name'],
                'units' => $row['units'],
                'revenue' => $row['revenue'],
            ];
        }

        return $ranked;
    }
}
