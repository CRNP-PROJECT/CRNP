<?php

declare(strict_types=1);

namespace App\Orders;

/**
 * An order as stored at `orders/{id}` (PLAN §4 *Data layer*).
 *
 * One shape serves both order kinds: a **walk-in** (rung up at the POS, split
 * tender) and an **online** pickup order (#23) — both draw an order code from
 * the same space. The lines freeze the selling price at ring-up, so the receipt
 * and the kitchen ticket never drift when the admin edits the menu afterwards.
 * Image bytes never live here — a proof screenshot is the sibling `proofs/{id}`
 * node.
 */
final readonly class Order
{
    /**
     * @param  list<LineItem>  $lines
     * @param  list<Payment>  $payments
     */
    public function __construct(
        public string $id,
        public string $code,
        public string $type,
        public OrderStatus $status,
        public array $lines,
        public float $total,
        public array $payments,
        public float $change,
        public ?string $gcashRef,
        public ?HandledBy $handledBy,
        public ?string $placedAt,
        public ?string $settledAt,
        public ?string $pickupAt = null,
        public ?string $deadlineAt = null,
        public ?string $customerUid = null,
        public bool $proofInFlight = false,
        public ?string $uploadStartedAt = null,
        public ?string $proofReceivedAt = null,
        public ?float $enteredAmount = null,
        public ?float $refundOwed = null,
        public ?string $flaggedAt = null,
        public ?string $verifiedAt = null,
        public ?string $rejectedAt = null,
        public ?string $table = null,
    ) {}

    /**
     * @param  array<mixed, mixed>  $node
     */
    public static function fromNode(string $id, array $node): ?self
    {
        $code = $node['code'] ?? null;
        $type = $node['type'] ?? null;
        $status = $node['status'] ?? null;

        if (! is_string($code) || ! is_string($type) || ! is_string($status)) {
            return null;
        }

        $orderStatus = OrderStatus::tryFrom($status);

        if ($orderStatus === null) {
            return null;
        }

        $total = $node['total'] ?? 0;
        $change = $node['change'] ?? 0;
        $gcashRef = $node['gcashRef'] ?? null;
        $placedAt = $node['placedAt'] ?? null;
        $settledAt = $node['settledAt'] ?? null;
        $handledBy = $node['handledBy'] ?? null;
        $pickupAt = $node['pickupAt'] ?? null;
        $deadlineAt = $node['deadlineAt'] ?? null;
        $customerUid = $node['customerUid'] ?? null;
        $proofInFlight = $node['proofInFlight'] ?? false;
        $uploadStartedAt = $node['uploadStartedAt'] ?? null;
        $proofReceivedAt = $node['proofReceivedAt'] ?? null;
        $enteredAmount = $node['enteredAmount'] ?? null;
        $refundOwed = $node['refundOwed'] ?? null;
        $flaggedAt = $node['flaggedAt'] ?? null;
        $verifiedAt = $node['verifiedAt'] ?? null;
        $rejectedAt = $node['rejectedAt'] ?? null;
        $table = $node['table'] ?? null;

        return new self(
            id: $id,
            code: $code,
            type: $type,
            status: $orderStatus,
            lines: self::linesFromNode($node['lines'] ?? null),
            total: is_numeric($total) ? (float) $total : 0.0,
            payments: self::paymentsFromNode($node['payments'] ?? null),
            change: is_numeric($change) ? (float) $change : 0.0,
            gcashRef: is_string($gcashRef) && $gcashRef !== '' ? $gcashRef : null,
            handledBy: is_array($handledBy) ? HandledBy::fromNode($handledBy) : null,
            placedAt: is_string($placedAt) ? $placedAt : null,
            settledAt: is_string($settledAt) ? $settledAt : null,
            pickupAt: is_string($pickupAt) && $pickupAt !== '' ? $pickupAt : null,
            deadlineAt: is_string($deadlineAt) && $deadlineAt !== '' ? $deadlineAt : null,
            customerUid: is_string($customerUid) && $customerUid !== '' ? $customerUid : null,
            proofInFlight: $proofInFlight === true,
            uploadStartedAt: is_string($uploadStartedAt) && $uploadStartedAt !== '' ? $uploadStartedAt : null,
            proofReceivedAt: is_string($proofReceivedAt) && $proofReceivedAt !== '' ? $proofReceivedAt : null,
            enteredAmount: is_numeric($enteredAmount) ? (float) $enteredAmount : null,
            refundOwed: is_numeric($refundOwed) ? (float) $refundOwed : null,
            flaggedAt: is_string($flaggedAt) && $flaggedAt !== '' ? $flaggedAt : null,
            verifiedAt: is_string($verifiedAt) && $verifiedAt !== '' ? $verifiedAt : null,
            rejectedAt: is_string($rejectedAt) && $rejectedAt !== '' ? $rejectedAt : null,
            table: is_string($table) && $table !== '' ? $table : null,
        );
    }

    public function isPending(): bool
    {
        return $this->status === OrderStatus::Pending;
    }

    /**
     * Whether a proof upload is mid-flight for this order. An upload sent with
     * no terminal response counts as in flight, so the expiry sweep (#25) holds
     * the order across 15:00 rather than dismissing it.
     */
    public function isMidUpload(): bool
    {
        return $this->proofInFlight;
    }

    /**
     * Whether the order tracker treats this order as the customer's active one
     * (reopenable with zero typing).
     */
    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    /**
     * @return list<LineItem>
     */
    private static function linesFromNode(mixed $node): array
    {
        if (! is_array($node)) {
            return [];
        }

        $lines = [];

        foreach ($node as $row) {
            if (! is_array($row)) {
                continue;
            }

            $line = LineItem::fromNode($row);

            if ($line !== null) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * @return list<Payment>
     */
    private static function paymentsFromNode(mixed $node): array
    {
        if (! is_array($node)) {
            return [];
        }

        $payments = [];

        foreach ($node as $row) {
            if (! is_array($row)) {
                continue;
            }

            $payment = Payment::fromNode($row);

            if ($payment !== null) {
                $payments[] = $payment;
            }
        }

        return $payments;
    }
}
