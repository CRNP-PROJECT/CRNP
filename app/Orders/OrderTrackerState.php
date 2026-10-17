<?php

declare(strict_types=1);

namespace App\Orders;

/**
 * The live state of an order as the customer's tracker sees it.
 *
 * `status` is the raw order state and `label` its customer-facing sentence, so
 * the tracker renders **whatever the order node says** — including the states
 * #24/#26/#27/#29 write — and degrades gracefully on one it does not recognise
 * (a friendly label, not a blank screen). The tracker is bound to the session
 * and `users/{uid}` via `customerUid`.
 *
 * A flagged hold carries `enteredAmount` — the figure the customer typed —
 * beside `total`, so the tracker can show both side by side beneath "Payment
 * needs checking" and let the customer self-correct. The image bytes never ride
 * here; the proof lives in the sibling `proofs/{id}` node.
 */
final readonly class OrderTrackerState
{
    public function __construct(
        public string $orderId,
        public string $code,
        public string $status,
        public string $label,
        public float $total,
        public ?float $enteredAmount,
        public ?string $pickupAt,
        public ?string $deadlineAt,
        public bool $isActive,
        public ?string $customerUid,
    ) {}

    /**
     * @param  array<mixed, mixed>  $node
     */
    public static function fromNode(string $orderId, array $node): ?self
    {
        $code = $node['code'] ?? null;

        if (! is_string($code) || $code === '') {
            return null;
        }

        $rawStatus = $node['status'] ?? null;
        $status = is_string($rawStatus) && $rawStatus !== '' ? $rawStatus : 'unknown';
        $known = OrderStatus::tryFrom($status);
        $total = $node['total'] ?? 0;
        $enteredAmount = $node['enteredAmount'] ?? null;
        $pickupAt = $node['pickupAt'] ?? null;
        $deadlineAt = $node['deadlineAt'] ?? null;
        $customerUid = $node['customerUid'] ?? null;

        return new self(
            orderId: $orderId,
            code: $code,
            status: $status,
            label: $known?->label() ?? 'Order status unavailable',
            total: is_numeric($total) ? (float) $total : 0.0,
            enteredAmount: is_numeric($enteredAmount) ? (float) $enteredAmount : null,
            pickupAt: is_string($pickupAt) && $pickupAt !== '' ? $pickupAt : null,
            deadlineAt: is_string($deadlineAt) && $deadlineAt !== '' ? $deadlineAt : null,
            isActive: $known?->isActive() ?? false,
            customerUid: is_string($customerUid) && $customerUid !== '' ? $customerUid : null,
        );
    }

    /**
     * The shape the tracker's poll endpoint returns.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'orderId' => $this->orderId,
            'code' => $this->code,
            'status' => $this->status,
            'label' => $this->label,
            'total' => $this->total,
            'enteredAmount' => $this->enteredAmount,
            'pickupAt' => $this->pickupAt,
            'deadlineAt' => $this->deadlineAt,
            'isActive' => $this->isActive,
        ];
    }
}
