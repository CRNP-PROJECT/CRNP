<?php

declare(strict_types=1);

namespace App\Stats;

use App\Orders\OrderType;
use App\Orders\PaymentMethod;
use App\Rentals\RentalStatus;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;

/**
 * The **daily tender** table behind the admin dashboard — one row per business
 * day of a month, each split four ways: orders cash / GCash and bookings cash /
 * GCash.
 *
 * It folds the two revenue streams the dashboard already shows as monthly cards
 * ({@see PaymentMix} for `sales/{id}`, {@see BookingMix} for `rentalBookings`)
 * into a single per-day view. Orders file under the business day of their
 * `soldAt`; bookings file under the business day of their `dateFrom` — the same
 * bounds the monthly cards read by, so a row here always sums to the card above
 * it.
 *
 * The rules for what counts and how the tender is split match the two mixes
 * exactly: an online order and a paid booking with no tender legs are GCash in
 * full, a walk-in counts each leg of its split, and only a paid booking counts.
 * The server computes; the human only reads.
 */
final readonly class DailyTender
{
    /** The statuses a booking has actually been paid for. */
    private const PAID_STATUSES = [
        RentalStatus::Confirmed->value,
        RentalStatus::HandedOver->value,
        RentalStatus::Returned->value,
    ];

    /**
     * @param  list<array{date: string, ordersCash: float, ordersGcash: float, bookingsCash: float, bookingsGcash: float, cashOrders: int, gcashOrders: int}>  $days
     */
    public function __construct(public array $days) {}

    /**
     * Bucket the month's order sales and rental bookings into one row per day.
     *
     * Alongside the amounts, each day counts how many **orders** were paid each
     * way (an online pickup is a GCash order, a split-tender walk-in counts on
     * both sides) — the same rule as {@see TenderCounts}.
     *
     * @param  list<array<string, mixed>>  $sales  order sales over the month
     * @param  list<array<string, mixed>>  $bookings  rental bookings starting in the month
     */
    public static function fromSalesAndBookings(array $sales, array $bookings): self
    {
        /** @var array<string, array{ordersCash: float, ordersGcash: float, bookingsCash: float, bookingsGcash: float, cashOrders: int, gcashOrders: int}> $byDay */
        $byDay = [];

        foreach ($sales as $sale) {
            $date = self::soldOn($sale['soldAt'] ?? null);

            if ($date === null) {
                continue;
            }

            $row = $byDay[$date] ?? self::blank();
            [$cash, $gcash] = self::salesTender($sale);
            $row['ordersCash'] = round($row['ordersCash'] + $cash, 2);
            $row['ordersGcash'] = round($row['ordersGcash'] + $gcash, 2);

            $methods = TenderCounts::methodsFor($sale);

            if ($methods['cash']) {
                $row['cashOrders']++;
            }

            if ($methods['gcash']) {
                $row['gcashOrders']++;
            }

            $byDay[$date] = $row;
        }

        foreach ($bookings as $booking) {
            $status = $booking['status'] ?? null;

            if (! is_string($status) || ! in_array($status, self::PAID_STATUSES, true)) {
                continue;
            }

            $dateFrom = $booking['dateFrom'] ?? null;

            if (! is_string($dateFrom) || $dateFrom === '') {
                continue;
            }

            $row = $byDay[$dateFrom] ?? self::blank();
            [$cash, $gcash] = self::bookingTender($booking);
            $row['bookingsCash'] = round($row['bookingsCash'] + $cash, 2);
            $row['bookingsGcash'] = round($row['bookingsGcash'] + $gcash, 2);
            $byDay[$dateFrom] = $row;
        }

        ksort($byDay);

        $days = [];

        foreach ($byDay as $date => $row) {
            $days[] = [
                'date' => $date,
                'ordersCash' => $row['ordersCash'],
                'ordersGcash' => $row['ordersGcash'],
                'bookingsCash' => $row['bookingsCash'],
                'bookingsGcash' => $row['bookingsGcash'],
                'cashOrders' => $row['cashOrders'],
                'gcashOrders' => $row['gcashOrders'],
            ];
        }

        return new self($days);
    }

    /**
     * The four columns summed across every day — the month totals.
     *
     * @return array{ordersCash: float, ordersGcash: float, bookingsCash: float, bookingsGcash: float, total: float}
     */
    public function totals(): array
    {
        $ordersCash = 0.0;
        $ordersGcash = 0.0;
        $bookingsCash = 0.0;
        $bookingsGcash = 0.0;

        foreach ($this->days as $day) {
            $ordersCash += $day['ordersCash'];
            $ordersGcash += $day['ordersGcash'];
            $bookingsCash += $day['bookingsCash'];
            $bookingsGcash += $day['bookingsGcash'];
        }

        return [
            'ordersCash' => round($ordersCash, 2),
            'ordersGcash' => round($ordersGcash, 2),
            'bookingsCash' => round($bookingsCash, 2),
            'bookingsGcash' => round($bookingsGcash, 2),
            'total' => round($ordersCash + $ordersGcash + $bookingsCash + $bookingsGcash, 2),
        ];
    }

    /**
     * @return array{ordersCash: float, ordersGcash: float, bookingsCash: float, bookingsGcash: float, cashOrders: int, gcashOrders: int}
     */
    private static function blank(): array
    {
        return [
            'ordersCash' => 0.0,
            'ordersGcash' => 0.0,
            'bookingsCash' => 0.0,
            'bookingsGcash' => 0.0,
            'cashOrders' => 0,
            'gcashOrders' => 0,
        ];
    }

    /**
     * The business date an order sold on, from its `soldAt` instant.
     */
    private static function soldOn(mixed $soldAt): ?string
    {
        if (! is_string($soldAt) || $soldAt === '') {
            return null;
        }

        return CarbonImmutable::parse($soldAt)->setTimezone(BusinessTime::timezone())->toDateString();
    }

    /**
     * An order sale's cash and GCash, by the same rule as {@see PaymentMix}: an
     * online order is GCash in full; a walk-in counts each leg of its split.
     *
     * @param  array<string, mixed>  $sale
     * @return array{float, float}
     */
    private static function salesTender(array $sale): array
    {
        $total = self::amount($sale['total'] ?? 0);

        if (($sale['type'] ?? null) === OrderType::Online->value) {
            return [0.0, $total];
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

        return [round($cash, 2), round($gcash, 2)];
    }

    /**
     * A booking's cash and GCash, by the same rule as {@see BookingMix}: a paid
     * booking with no tender legs was settled through the GCash gate in full.
     *
     * @param  array<string, mixed>  $booking
     * @return array{float, float}
     */
    private static function bookingTender(array $booking): array
    {
        $legs = self::payments($booking);

        if ($legs === []) {
            return [0.0, self::amount($booking['total'] ?? 0)];
        }

        $cash = 0.0;
        $gcash = 0.0;

        foreach ($legs as $payment) {
            $method = $payment['method'] ?? null;
            $amount = self::amount($payment['amount'] ?? 0);

            if ($method === PaymentMethod::Gcash->value) {
                $gcash += $amount;
            } elseif ($method === PaymentMethod::Cash->value) {
                $cash += $amount;
            }
        }

        return [round($cash, 2), round($gcash, 2)];
    }

    /**
     * The tender legs on a node, defensively read.
     *
     * @param  array<string, mixed>  $node
     * @return list<array<string, mixed>>
     */
    private static function payments(array $node): array
    {
        $payments = $node['payments'] ?? null;

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
