<?php

declare(strict_types=1);

namespace App\Rentals;

/**
 * The live state of a rental booking as the customer's tracker sees it.
 *
 * `status` is the raw booking state and `label` its customer-facing sentence,
 * so the tracker renders whatever the booking node says and degrades gracefully
 * on one it does not recognise. A flagged proof carries `enteredAmount` beside
 * `total`, so the customer can self-correct at the counter — no blame, no reason
 * codes. The proof image never rides here.
 */
final readonly class RentalTrackerState
{
    public function __construct(
        public string $bookingId,
        public string $code,
        public string $itemName,
        public string $status,
        public string $label,
        public float $total,
        public int $qty,
        public string $dateFrom,
        public string $dateTo,
        public ?float $enteredAmount,
        public ?string $deadlineAt,
        public ?string $returnDueAt,
        public bool $isActive,
        public string $uid,
    ) {}

    /**
     * @param  array<mixed, mixed>  $node
     */
    public static function fromNode(string $bookingId, array $node): ?self
    {
        $uid = $node['uid'] ?? null;
        $dateFrom = $node['dateFrom'] ?? null;
        $dateTo = $node['dateTo'] ?? null;

        if (! is_string($uid) || ! is_string($dateFrom) || ! is_string($dateTo)) {
            return null;
        }

        $rawStatus = $node['status'] ?? null;
        $status = is_string($rawStatus) && $rawStatus !== '' ? $rawStatus : 'unknown';
        $known = RentalStatus::tryFrom($status);
        $total = $node['total'] ?? 0;
        $qty = $node['qty'] ?? 1;
        $enteredAmount = $node['enteredAmount'] ?? null;
        $deadlineAt = $node['deadlineAt'] ?? null;
        $returnDueAt = $node['returnDueAt'] ?? null;

        return new self(
            bookingId: $bookingId,
            code: is_string($node['code'] ?? null) ? $node['code'] : '',
            itemName: is_string($node['itemName'] ?? null) ? $node['itemName'] : '',
            status: $status,
            label: $known?->label() ?? 'Booking status unavailable',
            total: is_numeric($total) ? (float) $total : 0.0,
            qty: is_numeric($qty) ? (int) $qty : 1,
            dateFrom: $dateFrom,
            dateTo: $dateTo,
            enteredAmount: is_numeric($enteredAmount) ? (float) $enteredAmount : null,
            deadlineAt: is_string($deadlineAt) && $deadlineAt !== '' ? $deadlineAt : null,
            returnDueAt: is_string($returnDueAt) && $returnDueAt !== '' ? $returnDueAt : null,
            isActive: $known?->isActive() ?? false,
            uid: $uid,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'bookingId' => $this->bookingId,
            'code' => $this->code,
            'itemName' => $this->itemName,
            'status' => $this->status,
            'label' => $this->label,
            'total' => $this->total,
            'qty' => $this->qty,
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
            'enteredAmount' => $this->enteredAmount,
            'deadlineAt' => $this->deadlineAt,
            'returnDueAt' => $this->returnDueAt,
            'isActive' => $this->isActive,
        ];
    }
}
