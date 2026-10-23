<?php

declare(strict_types=1);

namespace App\Stats;

use App\Orders\OrderType;
use App\Orders\PaymentMethod;

/**
 * The **tender counts** behind the dashboard's cash-versus-GCash chart — how
 * many orders were paid each way, as opposed to {@see PaymentMix}'s *how much*.
 *
 * The rules mirror the payment mix: an online pickup is GCash in full, so it
 * counts as one GCash order; a walk-in is counted by each tender leg it carries,
 * so a split-tender order counts on both sides (it genuinely used both). The
 * `orders` total is every sale in the window.
 *
 * The ledger carries no customer identity for a walk-in, so this counts
 * **orders**, not distinct people; the label says so rather than implying more.
 */
final readonly class TenderCounts
{
    public function __construct(
        public int $cashOrders,
        public int $gcashOrders,
        public int $orders,
    ) {}

    /**
     * Count the orders paid each way across the sales in the window.
     *
     * @param  list<array<string, mixed>>  $sales  order sales over the window
     */
    public static function fromSales(array $sales): self
    {
        $cash = 0;
        $gcash = 0;

        foreach ($sales as $sale) {
            $methods = self::methodsFor($sale);

            if ($methods['cash']) {
                $cash++;
            }

            if ($methods['gcash']) {
                $gcash++;
            }
        }

        return new self($cash, $gcash, count($sales));
    }

    /**
     * Whether one sale's tender reached cash and/or GCash.
     *
     * This is the single place the count rules live: an online pickup is GCash
     * in full, a walk-in is counted by each tender leg it carries (so a split
     * counts on both sides).
     *
     * @param  array<string, mixed>  $sale
     * @return array{cash: bool, gcash: bool}
     */
    public static function methodsFor(array $sale): array
    {
        if (($sale['type'] ?? null) === OrderType::Online->value) {
            return ['cash' => false, 'gcash' => true];
        }

        $cash = false;
        $gcash = false;

        foreach (self::payments($sale) as $payment) {
            $method = $payment['method'] ?? null;

            if ($method === PaymentMethod::Cash->value) {
                $cash = true;
            } elseif ($method === PaymentMethod::Gcash->value) {
                $gcash = true;
            }
        }

        return ['cash' => $cash, 'gcash' => $gcash];
    }

    /**
     * The tender legs on a sale row, defensively read.
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
}
