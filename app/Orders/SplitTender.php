<?php

declare(strict_types=1);

namespace App\Orders;

use InvalidArgumentException;

/**
 * The tender derived from the cashier's two typed amounts at settle (PLAN §4
 * *Cashier POS*).
 *
 * The cashier always uses the one **Cash + GCash** form: the cash received and
 * the GCash amount, either of which may be `0` when that method was not used.
 * The GCash leg is the typed GCash amount; the cash leg is the remaining
 * balance, so the payments always sum to the total. Change is whatever the cash
 * received exceeds that balance by — `0` for a GCash-only sale.
 *
 * The arithmetic runs in integer centavos, so the derived legs and the change
 * carry no floating-point drift. {@see assertBalanced()} re-checks the sum on
 * the write.
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

    public static function for(float $total, float $cashReceived, float $gcashTyped): self
    {
        $totalCents = self::cents($total);
        $cashCents = self::cents($cashReceived);
        $gcashCents = self::cents($gcashTyped);

        if ($cashCents < 0 || $gcashCents < 0) {
            throw new InvalidArgumentException('Tender amounts cannot be negative.');
        }

        if ($gcashCents > $totalCents) {
            throw new InvalidArgumentException('The GCash amount cannot exceed the order total.');
        }

        if ($cashCents + $gcashCents < $totalCents) {
            throw new InvalidArgumentException('The cash and GCash amounts must cover the order total.');
        }

        $appliedCashCents = $totalCents - $gcashCents;
        $changeCents = $cashCents + $gcashCents - $totalCents;

        $payments = [];

        if ($appliedCashCents > 0) {
            $payments[] = new Payment(PaymentMethod::Cash, self::pesos($appliedCashCents));
        }

        if ($gcashCents > 0) {
            $payments[] = new Payment(PaymentMethod::Gcash, self::pesos($gcashCents));
        }

        return new self(
            $payments,
            self::pesos($changeCents),
            $gcashCents > 0,
        );
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
