<?php

declare(strict_types=1);

namespace App\Orders;

/**
 * A scalar projection of an order for the cashier's online pickup queue
 * (PLAN §4 *Cashier POS* — online pickup queue).
 *
 * The queue, list, tracker, and board reads match `orders/{id}` only, so they
 * are scalar **by construction** — the proof image lives in the sibling
 * `proofs/{id}` node and never rides here. This object carries only what the
 * queue screen renders: the code and total, the payment deadline (awaiting
 * proof), and — for a flagged hold or a rejected proof — the entered amount
 * beside the total, plus the refund obligation a rejection books.
 */
final readonly class QueueOrder
{
    public function __construct(
        public string $id,
        public string $code,
        public float $total,
        public ?string $deadlineAt,
        public ?string $placedAt,
        public ?float $enteredAmount = null,
        public ?float $refundOwed = null,
    ) {}

    public static function fromOrder(Order $order): self
    {
        return new self(
            id: $order->id,
            code: $order->code,
            total: $order->total,
            deadlineAt: $order->deadlineAt,
            placedAt: $order->placedAt,
            enteredAmount: $order->enteredAmount,
            refundOwed: $order->refundOwed,
        );
    }

    /**
     * @return array{
     *     id: string,
     *     code: string,
     *     total: float,
     *     deadlineAt: ?string,
     *     placedAt: ?string,
     *     enteredAmount: ?float,
     *     refundOwed: ?float
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'total' => $this->total,
            'deadlineAt' => $this->deadlineAt,
            'placedAt' => $this->placedAt,
            'enteredAmount' => $this->enteredAmount,
            'refundOwed' => $this->refundOwed,
        ];
    }
}
