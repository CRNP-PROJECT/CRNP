<?php

declare(strict_types=1);

namespace App\Orders;

use InvalidArgumentException;

/**
 * The split tender derived from the one number the cashier typed: the cash
 * handed over (PLAN §4 *Cashier POS*).
 *
 * `cash ≥ total` → all-cash, `change = cash − total`, no GCash leg.
 * `cash < total` → `GCash = total − cash`, change `0`, and a GCash ref# is
 * required because that leg is `> 0`. The payments always sum to the total by
 * construction; {@see assertBalanced()} re-checks it on the write.
 *
 * The arithmetic runs in integer centavos, so the derived legs and the change
 * carry no floating-point drift.
 */
final readonly class SplitTender
{
    /**
     * @param  list<Payment>  $payments
     */
    private function __construct(
        public array $payments,
        public float $change,
        public bool $requiresGcashRef,
    ) {}

    public static function for(float $total, float $cashTyped): self
    {
        $totalCents = self::cents($total);
        $cashCents = max(0, self::cents($cashTyped));

        if ($cashCents >= $totalCents) {
            return new self(
                [new Payment(PaymentMethod::Cash, self::pesos($totalCents))],
                self::pesos($cashCents - $totalCents),
                false,
            );
        }

        $payments = [];

        if ($cashCents > 0) {
            $payments[] = new Payment(PaymentMethod::Cash, self::pesos($cashCents));
        }

        $gcashCents = $totalCents - $cashCents;

        if ($gcashCents > 0) {
            $payments[] = new Payment(PaymentMethod::Gcash, self::pesos($gcashCents));
        }

        return new self($payments, 0.0, $gcashCents > 0);
    }

    /**
     * The sum of the derived legs, in pesos.
     */
    public function sum(): float
    {
        $cents = 0;

        foreach ($this->payments as $payment) {
            $cents += self::cents($payment->amount);
        }

        return self::pesos($cents);
    }

    /**
     * Assert `Σ(payments) = total`, throwing when the tender has drifted.
     *
     * @param  list<Payment>  $payments
     */
    public static function assertBalanced(array $payments, float $total): void
    {
        $sum = 0;

        foreach ($payments as $payment) {
            $sum += self::cents($payment->amount);
        }

        if ($sum !== self::cents($total)) {
            throw new InvalidArgumentException(
                'The tender breakdown must sum to the order total.',
            );
        }
    }

    private static function cents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    private static function pesos(int $cents): float
    {
        return $cents / 100;
    }
}
