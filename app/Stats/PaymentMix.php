<?php

declare(strict_types=1);

namespace App\Stats;

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
            // An online order is prepaid GCash in full: the ledger row carries
            // no tender legs, so its whole total lands on the GCash side.
            if (($sale['type'] ?? null) === OrderType::Online->value) {
                $gcash += self::amount($sale['total'] ?? 0);

                continue;
            }

            foreach (self::payments($sale) as $payment) {
                $method = $payment['method'] ?? null;
                $amount = self::amount($payment['amount'] ?? 0);

                if ($method === PaymentMethod::Gcash->value) {
                    $gcash += $amount;
                } elseif ($method === PaymentMethod::Cash->value) {
                    $cash += $amount;
                }
            }
        }

        return new self(round($cash, 2), round($gcash, 2), count($sales));
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
