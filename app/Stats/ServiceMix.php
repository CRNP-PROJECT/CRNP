<?php

declare(strict_types=1);

namespace App\Stats;

use App\Orders\ServiceType;

/**
 * The **service mix** — the dine-in-vs-take-out split behind the admin
 * *Payments* report (PLAN §4 *Admin* — *Reports*).
 *
 * The split reads the same `sales/{id}` ledger the payment mix reads, so a
 * rental booking never counts. Each order sale snapshots its `serviceType` at
 * settle (and a backfilled historical sale records one too); a row written
 * before the field existed carries none and is bucketed as **unspecified**, so
 * an old ledgers never inflates either side.
 *
 * The server computes; the human only reads.
 */
final readonly class ServiceMix
{
    public function __construct(
        public float $dineIn,
        public float $takeOut,
        public float $unspecified,
        public int $dineInOrders,
        public int $takeOutOrders,
        public int $orders,
    ) {}

    /**
     * The collected total across every service type.
     */
    public function total(): float
    {
        return round($this->dineIn + $this->takeOut + $this->unspecified, 2);
    }

    /**
     * Sum the totals across the order sales in the range, split by service type.
     *
     * @param  list<array<string, mixed>>  $sales  order sales over the window
     */
    public static function fromSales(array $sales): self
    {
        $dineIn = 0.0;
        $takeOut = 0.0;
        $unspecified = 0.0;
        $dineInOrders = 0;
        $takeOutOrders = 0;

        foreach ($sales as $sale) {
            $amount = self::amount($sale['total'] ?? 0);
            $service = self::service($sale['serviceType'] ?? null);

            if ($service === ServiceType::DineIn) {
                $dineIn += $amount;
                $dineInOrders++;
            } elseif ($service === ServiceType::TakeOut) {
                $takeOut += $amount;
                $takeOutOrders++;
            } else {
                $unspecified += $amount;
            }
        }

        return new self(
            round($dineIn, 2),
            round($takeOut, 2),
            round($unspecified, 2),
            $dineInOrders,
            $takeOutOrders,
            count($sales),
        );
    }

    private static function service(mixed $value): ?ServiceType
    {
        return is_string($value) ? ServiceType::tryFrom($value) : null;
    }

    private static function amount(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }
}
