<?php

declare(strict_types=1);

namespace App\Orders;

/**
 * The **rejection record** written when a cashier rejects a proof (PLAN §4
 * *Cashier POS* — *Rejected*; CONTEXT — *Rejected*).
 *
 * It captures what a later audit needs — the order id, the entered amount the
 * customer claimed, the order total, the GCash ref#, who refused it, and when —
 * so the order row, the refund obligation, and the evidence survive even after
 * the image itself expires in 7 days (the proof cleanup sweep, #25). It is the
 * record the admin's Activity feed and Reports read back (#32).
 */
final readonly class Rejection
{
    public function __construct(
        public string $orderId,
        public ?float $enteredAmount,
        public float $orderTotal,
        public ?string $ref,
        public HandledBy $by,
        public string $at,
    ) {}

    /**
     * @return array{
     *     orderId: string,
     *     enteredAmount: ?float,
     *     orderTotal: float,
     *     ref: ?string,
     *     by: array{uid: string, name: string},
     *     at: string
     * }
     */
    public function toNode(): array
    {
        return [
            'orderId' => $this->orderId,
            'enteredAmount' => $this->enteredAmount,
            'orderTotal' => $this->orderTotal,
            'ref' => $this->ref,
            'by' => $this->by->toNode(),
            'at' => $this->at,
        ];
    }
}
