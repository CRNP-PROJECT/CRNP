<?php

declare(strict_types=1);

namespace App\Orders;

use InvalidArgumentException;

/**
 * The tender derived from the cashier's payment choice at settle (PLAN §4
 * *Cashier POS*).
 *
 * `Cash` → all-cash, `change = cash handed − total`, no GCash leg.
 * `GCash` → one GCash leg equal to the typed amount, which must equal the
 * total; no change, ref# required.
 * `Split` → one cash leg and one GCash leg, both typed by the cashier, which
 * must sum to the total; change `0`, ref# required.
 *
 * The payments always sum to the total by construction;
 * {@see assertBalanced()} re-checks it on the write. The arithmetic runs in
 * integer centavos, so the derived legs and the change carry no floating-point
 * drift.
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

    public static function for(TenderMethod $method, float $total, float $cashTyped, float $gcashTyped): self
    {
        return match ($method) {
            TenderMethod::Cash => self::cash($total, $cashTyped),
            TenderMethod::Gcash => self::gcash($total, $gcashTyped),
            TenderMethod::Split => self::split($total, $cashTyped, $gcashTyped),
        };
    }

    private static function cash(float $total, float $cashTyped): self
    {
        $totalCents = self::cents($total);
        $cashCents = self::cents($cashTyped);

        if ($cashCents < $totalCents) {
            throw new InvalidArgumentException('Cash received must cover the order total.');
        }

        return new self(
            [new Payment(PaymentMethod::Cash, self::pesos($totalCents))],
            self::pesos($cashCents - $totalCents),
            false,
        );
    }

    private static function gcash(float $total, float $gcashTyped): self
    {
        $totalCents = self::cents($total);
        $gcashCents = self::cents($gcashTyped);

        if ($gcashCents !== $totalCents) {
            throw new InvalidArgumentException('The GCash amount must equal the order total.');
        }

        return new self(
            [new Payment(PaymentMethod::Gcash, self::pesos($gcashCents))],
            0.0,
            true,
        );
    }

    private static function split(float $total, float $cashTyped, float $gcashTyped): self
    {
        $totalCents = self::cents($total);
        $cashCents = self::cents($cashTyped);
        $gcashCents = self::cents($gcashTyped);

        if ($cashCents <= 0 || $gcashCents <= 0 || $cashCents + $gcashCents !== $totalCents) {
            throw new InvalidArgumentException(
                'A split needs cash and GCash amounts that sum to the order total.',
            );
        }

        return new self(
            [
                new Payment(PaymentMethod::Cash, self::pesos($cashCents)),
                new Payment(PaymentMethod::Gcash, self::pesos($gcashCents)),
            ],
            0.0,
            true,
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
