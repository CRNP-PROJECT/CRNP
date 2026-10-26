<?php

declare(strict_types=1);

namespace App\Stats;

use App\Orders\HandledBy;
use App\Orders\OrderType;
use App\Orders\PaymentMethod;

/**
 * The **payment mix** — the GCash-vs-cash totals behind the admin *Payments*
 * report (PLAN §4 *Admin* — *Reports*).
 *
 * The split reads the same `sales/{id}` ledger the dashboard and sales report
 * read, so a rental booking never counts. Two tender sources feed it:
 *
 * - a **walk-in** records its tender legs as `payments: [{method, amount}]`
 *   (`cash` / `gcash`) at settle, so each leg is summed by its method;
 * - an **online** pickup is prepaid through the GCash gate and its ledger row
 *   carries no tender legs, so its whole total is GCash.
 *
 * The same rows already carry `handledBy`, so **who took the cash and who took
 * the GCash** needs no extra field: {@see byCashier()} groups the ledger by that
 * stamp and sums the same tender legs per cashier.
 *
 * The server computes; the human only reads.
 */
final readonly class PaymentMix
{
    public function __construct(
        public float $cash,
        public float $gcash,
        public int $orders,
    ) {}

    /**
     * The collected total across both methods.
     */
    public function total(): float
    {
        return round($this->cash + $this->gcash, 2);
    }

    /**
     * Sum the tender across the order sales in the range.
     *
     * @param  list<array<string, mixed>>  $sales  order sales over the window
     */
    public static function fromSales(array $sales): self
    {
        $cash = 0.0;
        $gcash = 0.0;

        foreach ($sales as $sale) {
            [$saleCash, $saleGcash] = self::tender($sale);

            $cash += $saleCash;
            $gcash += $saleGcash;
        }

        return new self(round($cash, 2), round($gcash, 2), count($sales));
    }

    /**
     * The same tender split, grouped by the cashier stamped on each sale row.
     *
     * `handledBy` already rides on the sale, so the per-cashier cash-vs-GCash
     * figures are derived rather than stored twice. A backfilled sale carries no
     * stamp and stays out, matching the per-cashier Activity totals.
     *
     * @param  list<array<string, mixed>>  $sales  order sales over the window
     * @return list<array{uid: string, name: string, cash: float, gcash: float, orders: int, total: float}>
     */
    public static function byCashier(array $sales): array
    {
        /** @var array<string, array{name: string, cash: float, gcash: float, orders: int}> $tally */
        $tally = [];

        foreach ($sales as $sale) {
            $handledBy = is_array($sale['handledBy'] ?? null) ? HandledBy::fromNode($sale['handledBy']) : null;

            if ($handledBy === null) {
                continue;
            }

            [$cash, $gcash] = self::tender($sale);

            $current = $tally[$handledBy->uid] ?? [
                'name' => $handledBy->name,
                'cash' => 0.0,
                'gcash' => 0.0,
                'orders' => 0,
            ];

            $tally[$handledBy->uid] = [
                'name' => $current['name'],
                'cash' => round($current['cash'] + $cash, 2),
                'gcash' => round($current['gcash'] + $gcash, 2),
                'orders' => $current['orders'] + 1,
            ];
        }

        $rows = [];

        foreach ($tally as $uid => $row) {
            $rows[] = [
                'uid' => $uid,
                'name' => $row['name'],
                'cash' => $row['cash'],
                'gcash' => $row['gcash'],
                'orders' => $row['orders'],
                'total' => round($row['cash'] + $row['gcash'], 2),
            ];
        }

        usort($rows, static fn(array $a, array $b): int => $b['total'] <=> $a['total']);

        return $rows;
    }

    /**
     * The cash and GCash collected by one sale row.
     *
     * An online order is prepaid GCash in full: the ledger row carries no tender
     * legs, so its whole total lands on the GCash side.
     *
     * @param  array<string, mixed>  $sale
     * @return array{float, float}
     */
    private static function tender(array $sale): array
    {
        if (($sale['type'] ?? null) === OrderType::Online->value) {
            return [0.0, self::amount($sale['total'] ?? 0)];
        }

        $cash = 0.0;
        $gcash = 0.0;

        foreach (self::payments($sale) as $payment) {
            $method = $payment['method'] ?? null;
            $amount = self::amount($payment['amount'] ?? 0);

            if ($method === PaymentMethod::Gcash->value) {
                $gcash += $amount;
            } elseif ($method === PaymentMethod::Cash->value) {
                $cash += $amount;
            }
        }

        return [$cash, $gcash];
    }

    /**
     * The tender legs on a walk-in sale row, defensively read.
     *
     * @param  array<string, mixed>  $sale
     * @return list<array<string, mixed>>
     */
    private static function payments(array $sale): array
    {
        $payments = $sale['payments'] ?? null;

        if (! is_array($payments)) {
            return [];
        }

        $rows = [];

        foreach ($payments as $payment) {
            if (! is_array($payment)) {
                continue;
            }

            $row = [];

            foreach ($payment as $field => $value) {
                $row[(string) $field] = $value;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    private static function amount(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }
}
