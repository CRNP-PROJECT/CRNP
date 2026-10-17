<?php

declare(strict_types=1);

namespace App\Orders;

/**
 * One tender leg of an order: `payments: [{method, amount}]` (PLAN §4 *Cashier
 * POS*). The set of legs always sums to the order total — asserted on write.
 */
final readonly class Payment
{
    public function __construct(
        public PaymentMethod $method,
        public float $amount,
    ) {}

    /**
     * @param  array<mixed, mixed>  $node
     */
    public static function fromNode(array $node): ?self
    {
        $method = $node['method'] ?? null;
        $amount = $node['amount'] ?? null;

        if (! is_string($method) || ! is_numeric($amount)) {
            return null;
        }

        $paymentMethod = PaymentMethod::tryFrom($method);

        if ($paymentMethod === null) {
            return null;
        }

        return new self($paymentMethod, (float) $amount);
    }

    /**
     * @return array{method: string, amount: float}
     */
    public function toNode(): array
    {
        return ['method' => $this->method->value, 'amount' => $this->amount];
    }
}
