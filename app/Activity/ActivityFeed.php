<?php

declare(strict_types=1);

namespace App\Activity;

use App\Orders\HandledBy;
use App\Rtdb\Contracts\RtdbClient;
use App\Rtdb\RtdbQuery;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;

/**
 * The admin **Activity** feed and the per-cashier daily totals (PLAN §4 *Admin*
 * — *Reports*; CONTEXT — *Activity* / *Handled by*).
 *
 * The feed is *time · who · action · order code*, **latest N and bounded** —
 * never an unbounded read. Two sources feed it: the `sales/{id}` ledger (whose
 * `handledBy` stamp records Mark served / Settled walk-in / Collect late) and the
 * `audit/{id}` records (Restore). Both reads use a bounded `limitToLast` on an
 * indexed field, so the feed costs a fixed number of rows regardless of how long
 * the diner has traded.
 *
 * The **per-cashier daily totals** group the same sale rows by `handledBy` — the
 * field rides on the sale, so the totals need no extra reads — for the business
 * day the feed is opened on.
 */
final readonly class ActivityFeed
{
    /** How many rows the bounded feed returns, newest last. */
    public const LATEST = 20;

    public function __construct(private RtdbClient $client) {}

    /**
     * The latest Activity entries, newest first.
     *
     * @return list<ActivityEntry>
     */
    public function latest(): array
    {
        $entries = [];

        $sales = $this->client->query(
            'sales',
            RtdbQuery::orderBy('updatedAt')->limitToLast(self::LATEST),
        );

        foreach ($sales as $row) {
            $handledBy = is_array($row['handledBy'] ?? null) ? HandledBy::fromNode($row['handledBy']) : null;

            if ($handledBy === null) {
                continue;
            }

            $type = is_string($row['type'] ?? null) ? $row['type'] : '';
            $at = is_string($row['soldAt'] ?? null) ? $row['soldAt'] : '';

            $entries[] = new ActivityEntry(
                at: $at,
                who: $handledBy->name,
                action: ActivityEntry::saleAction($type),
                code: is_string($row['code'] ?? null) ? $row['code'] : '—',
            );
        }

        $audit = $this->client->query(
            'audit',
            RtdbQuery::orderBy('updatedAt')->limitToLast(self::LATEST),
        );

        foreach ($audit as $row) {
            $who = is_array($row['who'] ?? null) ? HandledBy::fromNode($row['who']) : null;

            if ($who === null) {
                continue;
            }

            $entries[] = new ActivityEntry(
                at: is_string($row['when'] ?? null) ? $row['when'] : '',
                who: $who->name,
                action: ActivityEntry::auditAction(is_string($row['action'] ?? null) ? $row['action'] : ''),
                code: is_string($row['code'] ?? null) ? $row['code'] : '—',
            );
        }

        usort($entries, static fn(ActivityEntry $a, ActivityEntry $b): int => strcmp($b->at, $a->at));

        return array_slice($entries, 0, self::LATEST);
    }

    /**
     * Per-cashier totals for a business day, highest total first.
     *
     * @param  string  $date  the business date, `Y-m-d`
     * @return list<array{uid: string, name: string, orders: int, total: float}>
     */
    public function dailyTotals(string $date): array
    {
        [$from, $to] = self::utcRange($date);

        $rows = $this->client->query('sales', RtdbQuery::orderBy('soldAt')->range($from, $to));

        /** @var array<string, array{name: string, orders: int, total: float}> $tally */
        $tally = [];

        foreach ($rows as $row) {
            $handledBy = is_array($row['handledBy'] ?? null) ? HandledBy::fromNode($row['handledBy']) : null;

            if ($handledBy === null) {
                continue;
            }

            $total = $row['total'] ?? 0;
            $amount = is_numeric($total) ? (float) $total : 0.0;
            $current = $tally[$handledBy->uid] ?? ['name' => $handledBy->name, 'orders' => 0, 'total' => 0.0];

            $tally[$handledBy->uid] = [
                'name' => $current['name'],
                'orders' => $current['orders'] + 1,
                'total' => round($current['total'] + $amount, 2),
            ];
        }

        $totals = [];

        foreach ($tally as $uid => $row) {
            $totals[] = ['uid' => $uid, 'name' => $row['name'], 'orders' => $row['orders'], 'total' => $row['total']];
        }

        usort($totals, static fn(array $a, array $b): int => $b['total'] <=> $a['total']);

        return $totals;
    }

    /**
     * The UTC instant range covering a business date in Asia/Manila.
     *
     * @return array{string, string}
     */
    private static function utcRange(string $date): array
    {
        $start = CarbonImmutable::parse($date, BusinessTime::timezone())->startOfDay();
        $end = $start->endOfDay();

        return [$start->toIso8601ZuluString(), $end->toIso8601ZuluString()];
    }
}
