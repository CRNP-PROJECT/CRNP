<?php

declare(strict_types=1);

namespace App\Stats;

use App\Orders\LineItem;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;

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

    /**
     * Rank the products and split each one's units across the given days.
     *
     * The dashboard's per-day best-sellers chart needs the same ranking as the
     * table, plus one units figure per day in `$window` (oldest first). A line
     * whose sale carries no readable `soldAt` still counts toward the total but
     * not toward any day, the same tolerance as the trend.
     *
     * @param  list<array<string, mixed>>  $sales
     * @param  list<string>  $window  business dates (`Y-m-d`), oldest first
     * @return list<array{productId: string, name: string, units: int, revenue: float, days: list<int>}>
     */
    public static function rankByDay(array $sales, array $window, int $limit): array
    {
        $positions = array_flip($window);
        $blankDays = array_fill(0, count($window), 0);

        /** @var array<string, array{name: string, units: int, revenue: float, days: list<int>}> $tally */
        $tally = [];

        foreach ($sales as $sale) {
            $soldAt = $sale['soldAt'] ?? null;
            $position = null;

            if (is_string($soldAt) && $soldAt !== '') {
                $date = CarbonImmutable::parse($soldAt)
                    ->setTimezone(BusinessTime::timezone())
                    ->toDateString();
                $position = $positions[$date] ?? null;
            }

            $lines = is_array($sale['lines'] ?? null) ? $sale['lines'] : [];

            foreach ($lines as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $line = LineItem::fromNode($row);

                if ($line === null) {
                    continue;
                }

                $current = $tally[$line->productId] ?? [
                    'name' => $line->name,
                    'units' => 0,
                    'revenue' => 0.0,
                    'days' => $blankDays,
                ];

                $current['units'] += $line->quantity;
                $current['revenue'] = round($current['revenue'] + $line->lineTotal(), 2);

                if (is_int($position)) {
                    $current['days'][$position] += $line->quantity;
                }

                $tally[$line->productId] = $current;
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
                'days' => array_values($row['days']),
            ];
        }

        return $ranked;
    }
}
