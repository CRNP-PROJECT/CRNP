<?php

declare(strict_types=1);

namespace App\Stats;

use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The dashboard's computed figures (PLAN §4 *Admin* — *Dashboard*).
 *
 * Everything is derived server-side from the sales ledger: the KPIs for today,
 * a 7-day trend, the best-sellers, and the peak hours. The window is bounded by
 * the indexed `soldAt` field, and the ledger only returns order sales — a rental
 * booking (#34) is excluded from the product ranking and the trend, so the
 * dashboard ranks products only. The server computes; the human only reads.
 */
final readonly class DashboardMetrics
{
    /** The length of the trend window, in days (including today). */
    public const WINDOW_DAYS = 7;

    /** How many best-sellers the dashboard lists. */
    public const BEST_SELLER_LIMIT = 5;

    /**
     * @param  list<array{date: string, total: float, orders: int}>  $trend
     * @param  list<array{productId: string, name: string, units: int, revenue: float}>  $bestSellers
     * @param  list<array{productId: string, name: string, units: int, revenue: float, days: list<int>}>  $productTrend
     * @param  list<array{hour: string, orders: int, total: float}>  $peakHours
     */
    public function __construct(
        public float $salesToday,
        public int $ordersToday,
        public float $salesWindow,
        public int $ordersWindow,
        public array $trend,
        public array $bestSellers,
        public array $productTrend,
        public array $peakHours,
    ) {}

    /**
     * Compute the metrics for the seven days ending on $now's business date.
     *
     * @param  list<array<string, mixed>>  $sales  order sales over the window
     */
    public static function fromSales(array $sales, CarbonInterface $now): self
    {
        $today = self::businessDate($now);
        $days = self::days($now);

        /** @var array<string, float> $totalsByDay */
        $totalsByDay = array_fill_keys($days, 0.0);
        /** @var array<string, int> $ordersByDay */
        $ordersByDay = array_fill_keys($days, 0);
        /** @var array<string, array{orders: int, total: float}> $byHour */
        $byHour = [];

        $salesToday = 0.0;
        $ordersToday = 0;

        foreach ($sales as $sale) {
            $soldAt = $sale['soldAt'] ?? null;
            $total = $sale['total'] ?? 0;
            $amount = is_numeric($total) ? (float) $total : 0.0;

            if (! is_string($soldAt) || $soldAt === '') {
                continue;
            }

            $at = CarbonImmutable::parse($soldAt)->setTimezone(BusinessTime::timezone());
            $date = $at->toDateString();
            $hour = $at->format('H:00');

            if (array_key_exists($date, $totalsByDay)) {
                $totalsByDay[$date] += $amount;
                $ordersByDay[$date]++;
            }

            $current = $byHour[$hour] ?? ['orders' => 0, 'total' => 0.0];
            $byHour[$hour] = ['orders' => $current['orders'] + 1, 'total' => round($current['total'] + $amount, 2)];

            if ($date === $today) {
                $salesToday += $amount;
                $ordersToday++;
            }
        }

        $trend = [];

        foreach ($days as $date) {
            $trend[] = [
                'date' => $date,
                'total' => round($totalsByDay[$date], 2),
                'orders' => $ordersByDay[$date],
            ];
        }

        $peakHours = [];

        foreach ($byHour as $hour => $row) {
            $peakHours[] = ['hour' => $hour, 'orders' => $row['orders'], 'total' => $row['total']];
        }

        usort($peakHours, static function (array $a, array $b): int {
            return $b['orders'] <=> $a['orders'] ?: strcmp($a['hour'], $b['hour']);
        });

        $ranked = ProductTally::rankByDay($sales, $days, self::BEST_SELLER_LIMIT);

        $bestSellers = [];
        $productTrend = [];

        foreach ($ranked as $row) {
            $bestSellers[] = [
                'productId' => $row['productId'],
                'name' => $row['name'],
                'units' => $row['units'],
                'revenue' => $row['revenue'],
            ];
            $productTrend[] = $row;
        }

        return new self(
            salesToday: round($salesToday, 2),
            ordersToday: $ordersToday,
            salesWindow: round(array_sum($totalsByDay), 2),
            ordersWindow: array_sum($ordersByDay),
            trend: $trend,
            bestSellers: $bestSellers,
            productTrend: $productTrend,
            peakHours: $peakHours,
        );
    }

    /**
     * The seven business dates ending on $now, oldest first.
     *
     * @return list<string>
     */
    private static function days(CarbonInterface $now): array
    {
        $today = CarbonImmutable::instance($now)->setTimezone(BusinessTime::timezone())->startOfDay();
        $days = [];

        for ($offset = self::WINDOW_DAYS - 1; $offset >= 0; $offset--) {
            $days[] = $today->subDays($offset)->toDateString();
        }

        return $days;
    }

    private static function businessDate(CarbonInterface $now): string
    {
        return CarbonImmutable::instance($now)->setTimezone(BusinessTime::timezone())->toDateString();
    }
}
