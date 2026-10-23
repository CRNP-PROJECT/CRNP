<?php

declare(strict_types=1);

namespace App\Stats;

use App\Support\BusinessTime;
use Carbon\CarbonImmutable;

/**
 * The **daily sales series** behind the sales report's bar chart.
 *
 * It folds the range's order sales into one bar per business day — the Manila
 * day of each row's `soldAt`, matching the dashboard's bucketing — and
 * zero-fills days with no sales so the chart reads as a true timeline, not a
 * scatter of active days.
 *
 * A range can be arbitrarily long; a bar per day stops being legible past a
 * couple of months. Past {@see self::MAX_DAYS} the series keeps the most recent
 * window and flags itself truncated, so the view can say so rather than draw a
 * thousand hairlines.
 */
final readonly class DailySales
{
    /** The widest window a per-day bar chart stays legible for. */
    public const MAX_DAYS = 62;

    /**
     * @param  list<array{date: string, total: float, orders: int}>  $days
     */
    public function __construct(
        public array $days,
        public bool $truncated = false,
    ) {}

    /**
     * Bucket the range's sales into one zero-filled row per business day.
     *
     * @param  list<array<string, mixed>>  $sales  order sales over the range
     */
    public static function fromSalesInRange(array $sales, string $from, string $to): self
    {
        /** @var array<string, array{total: float, orders: int}> $byDay */
        $byDay = [];

        foreach ($sales as $sale) {
            $soldAt = $sale['soldAt'] ?? null;

            if (! is_string($soldAt) || $soldAt === '') {
                continue;
            }

            $date = CarbonImmutable::parse($soldAt)->setTimezone(BusinessTime::timezone())->toDateString();
            $total = $sale['total'] ?? 0;
            $row = $byDay[$date] ?? ['total' => 0.0, 'orders' => 0];
            $row['total'] = round($row['total'] + (is_numeric($total) ? (float) $total : 0.0), 2);
            $row['orders']++;
            $byDay[$date] = $row;
        }

        $start = CarbonImmutable::parse($from)->startOfDay();
        $end = CarbonImmutable::parse($to)->startOfDay();

        if ($end->lessThan($start)) {
            [$start, $end] = [$end, $start];
        }

        $truncated = false;

        if ((int) $start->diffInDays($end) + 1 > self::MAX_DAYS) {
            $start = $end->subDays(self::MAX_DAYS - 1);
            $truncated = true;
        }

        $days = [];

        for ($day = $start; $day->lessThanOrEqualTo($end); $day = $day->addDay()) {
            $key = $day->toDateString();
            $days[] = [
                'date' => $key,
                'total' => $byDay[$key]['total'] ?? 0.0,
                'orders' => $byDay[$key]['orders'] ?? 0,
            ];
        }

        return new self($days, $truncated);
    }

    /**
     * The tallest bar's height, so the view can scale every bar against it.
     */
    public function peak(): float
    {
        $peak = 0.0;

        foreach ($this->days as $day) {
            $peak = max($peak, $day['total']);
        }

        return $peak;
    }
}
