<?php

declare(strict_types=1);

namespace App\Stats;

use App\Orders\PaymentMethod;
use App\Rentals\RentalStatus;

/**
 * The **booking payment mix** — the GCash-vs-cash totals behind the admin
 * dashboard's monthly booking KPIs.
 *
 * A rental booking is prepaid through the same GCash gate as an order, and only
 * a **paid** booking counts: `confirmed`, `handed_over`, or `returned`. An
 * awaiting-payment window, a flagged hold, a lapsed window, or a cancellation
 * never reaches revenue. Tender rides on the booking node as
 * `payments: [{method, amount}]`; a booking confirmed without tender legs (the
 * counter's Confirm on a flagged hold) was settled in full through GCash, so
 * its whole total lands on the GCash side — the same rule the order mix applies
 * to an online pickup.
 *
 * The server computes; the human only reads.
 */
final readonly class BookingMix
{
    /** The statuses a booking has actually been paid for. */
    private const PAID_STATUSES = [
        RentalStatus::Confirmed->value,
        RentalStatus::HandedOver->value,
        RentalStatus::Returned->value,
    ];

    public function __construct(
        public float $cash,
        public float $gcash,
        public int $bookings,
    ) {}

    /**
     * The collected total across both methods.
     */
    public function total(): float
    {
        return round($this->cash + $this->gcash, 2);
    }

    /**
     * Sum the tender across the paid bookings in the range.
     *
     * @param  list<array<string, mixed>>  $bookings  rental bookings, any status
     */
    public static function fromBookings(array $bookings): self
    {
        $cash = 0.0;
        $gcash = 0.0;
        $count = 0;

        foreach ($bookings as $booking) {
            $status = $booking['status'] ?? null;

            if (! is_string($status) || ! in_array($status, self::PAID_STATUSES, true)) {
                continue;
            }

            $count++;
            $total = self::amount($booking['total'] ?? 0);
            $legs = self::payments($booking);

            // A paid booking with no tender legs was settled in full through
            // the prepaid GCash gate — the counter's Confirm writes no legs.
            if ($legs === []) {
                $gcash += $total;

                continue;
            }

            foreach ($legs as $payment) {
                $method = $payment['method'] ?? null;
                $amount = self::amount($payment['amount'] ?? 0);

                if ($method === PaymentMethod::Gcash->value) {
                    $gcash += $amount;
                } elseif ($method === PaymentMethod::Cash->value) {
                    $cash += $amount;
                }
            }
        }

        return new BookingMix(round($cash, 2), round($gcash, 2), $count);
    }

    /**
     * The tender legs on a booking node, defensively read.
     *
     * @param  array<string, mixed>  $booking
     * @return list<array<string, mixed>>
     */
    private static function payments(array $booking): array
    {
        $payments = $booking['payments'] ?? null;

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
