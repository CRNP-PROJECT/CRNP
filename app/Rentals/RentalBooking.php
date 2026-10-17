<?php

declare(strict_types=1);

namespace App\Rentals;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * A rental booking as stored at `rentalBookings/{id}` (CONTEXT — *Rental
 * booking*).
 *
 * It is a **separate stream** from orders: the kitchen board and Top 3 never
 * see it, and it is never written to `sales/{id}`. The node carries the item id
 * and the frozen per-day rate (so a later price edit never rewrites history),
 * the inclusive date range, the quantity, and the computed total. Payment rides
 * on this same node — never a sibling `sales` row — so the rental ledger is
 * self-contained.
 *
 * A booking is created `awaiting_payment` with a 15-minute window
 * (`deadlineAt`); a matching proof auto-verifies it to `confirmed` (units
 * reserved, still on hand); the counter's **Hand over** moves it to
 * `handed_over` (stock fell) and **Confirm return** to `returned` (stock rose).
 * An unpaid booking `lapses` with stock untouched.
 */
final readonly class RentalBooking
{
    public function __construct(
        public string $id,
        public string $code,
        public string $itemId,
        public string $uid,
        public RentalPeriod $period,
        public int $qty,
        public float $total,
        public RentalStatus $status,
        public float $pricePerDay,
        public ?string $deadlineAt = null,
        public ?string $returnDueAt = null,
        public ?string $placedAt = null,
        public ?string $handedOverAt = null,
        public ?string $returnedAt = null,
        public ?string $lapsedAt = null,
        public ?string $cancelledAt = null,
        public ?string $terminalAt = null,
        public ?string $gcashRef = null,
        public ?float $enteredAmount = null,
        public bool $proofInFlight = false,
        public ?string $proofReceivedAt = null,
        public ?string $verifiedAt = null,
    ) {}

    /**
     * @param  array<mixed, mixed>  $node
     */
    public static function fromNode(string $id, array $node): ?self
    {
        $itemId = $node['itemId'] ?? null;
        $uid = $node['uid'] ?? null;
        $dateFrom = $node['dateFrom'] ?? null;
        $dateTo = $node['dateTo'] ?? null;
        $status = $node['status'] ?? null;

        if (! is_string($itemId) || $itemId === '' || ! is_string($uid)
            || ! is_string($dateFrom) || ! is_string($dateTo)
            || ! is_string($status)) {
            return null;
        }

        $rentalStatus = RentalStatus::tryFrom($status);

        if ($rentalStatus === null) {
            return null;
        }

        try {
            $period = RentalPeriod::fromStrings($dateFrom, $dateTo);
        } catch (\InvalidArgumentException) {
            return null;
        }

        $qty = $node['qty'] ?? 1;
        $total = $node['total'] ?? 0;
        $pricePerDay = $node['pricePerDay'] ?? 0;
        $enteredAmount = $node['enteredAmount'] ?? null;

        return new self(
            id: $id,
            code: is_string($node['code'] ?? null) ? $node['code'] : '',
            itemId: $itemId,
            uid: $uid,
            period: $period,
            qty: is_numeric($qty) ? (int) $qty : 1,
            total: is_numeric($total) ? (float) $total : 0.0,
            status: $rentalStatus,
            pricePerDay: is_numeric($pricePerDay) ? (float) $pricePerDay : 0.0,
            deadlineAt: self::stringOrNull($node['deadlineAt'] ?? null),
            returnDueAt: self::stringOrNull($node['returnDueAt'] ?? null),
            placedAt: self::stringOrNull($node['placedAt'] ?? null),
            handedOverAt: self::stringOrNull($node['handedOverAt'] ?? null),
            returnedAt: self::stringOrNull($node['returnedAt'] ?? null),
            lapsedAt: self::stringOrNull($node['lapsedAt'] ?? null),
            cancelledAt: self::stringOrNull($node['cancelledAt'] ?? null),
            terminalAt: self::stringOrNull($node['terminalAt'] ?? null),
            gcashRef: self::stringOrNull($node['gcashRef'] ?? null),
            enteredAmount: is_numeric($enteredAmount) ? (float) $enteredAmount : null,
            proofInFlight: ($node['proofInFlight'] ?? false) === true,
            proofReceivedAt: self::stringOrNull($node['proofReceivedAt'] ?? null),
            verifiedAt: self::stringOrNull($node['verifiedAt'] ?? null),
        );
    }

    /**
     * Whether the units are reserved but not yet handed over — the bookings
     * that overlap-subtract from on-hand stock.
     */
    public function reservesStock(): bool
    {
        return $this->status->reservesStock();
    }

    /**
     * Whether the booking's return is past due and not yet returned — the quiet
     * overdue staff row (no auto-penalty in v1).
     */
    public function isOverdueAt(DateTimeInterface $now): bool
    {
        if ($this->status !== RentalStatus::HandedOver || $this->returnDueAt === null) {
            return false;
        }

        return CarbonImmutable::parse($this->returnDueAt)->getTimestamp()
            < CarbonImmutable::instance($now)->getTimestamp();
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
