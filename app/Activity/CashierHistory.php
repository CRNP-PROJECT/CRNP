<?php

declare(strict_types=1);

namespace App\Activity;

use App\Orders\HandledBy;
use App\Rentals\RentalStatus;
use App\Rtdb\Contracts\RtdbClient;
use App\Rtdb\RtdbQuery;

/**
 * The admin **Cashier history** feed (CONTEXT — *Activity* / *Handled by*).
 *
 * It answers *which cashier accepted or rejected which customer* — for orders
 * and rental bookings alike — as *time · cashier · decision · subject · code ·
 * detail*, **latest N and bounded**, never an unbounded read. Three sources
 * feed it, each read with a bounded `limitToLast` on the indexed `updatedAt`
 * field:
 *
 * - the `sales/{id}` ledger — an **Accepted · Order** row: the `handledBy`
 *   stamp Mark served / Settled walk-in / Collect late leaves behind;
 * - the `orders/{id}` rows — a **Rejected · Order** row: the `rejection`
 *   record Reject writes (who, when, claimed vs total); an Approve-undo clears
 *   the record, so a reversed rejection leaves the history;
 * - the `rentalBookings/{id}` rows — a **Booking** row: the `handledBy` stamp
 *   Confirm / Hand over / Confirm return / Cancel leave behind. A cancelled
 *   booking reads **Rejected**; a confirmed, handed-over, or returned one reads
 *   **Accepted**. An awaiting-payment window, a flagged hold, and a lapse carry
 *   no counter decision and never appear.
 */
final readonly class CashierHistory
{
    /** How many rows each bounded source read returns. */
    public const LATEST = 30;

    public function __construct(private RtdbClient $client) {}

    /**
     * The latest cashier decisions, newest first.
     *
     * @return list<CashierHistoryEntry>
     */
    public function latest(): array
    {
        $entries = [
            ...$this->acceptedOrders(),
            ...$this->rejectedOrders(),
            ...$this->bookings(),
        ];

        usort($entries, static fn(CashierHistoryEntry $a, CashierHistoryEntry $b): int => strcmp($b->at, $a->at));

        return array_slice($entries, 0, self::LATEST);
    }

    /**
     * Order sales stamped with the cashier who settled or served them.
     *
     * @return list<CashierHistoryEntry>
     */
    private function acceptedOrders(): array
    {
        $entries = [];

        $sales = $this->client->query(
            'sales',
            RtdbQuery::orderBy('updatedAt')->limitToLast(self::LATEST),
        );

        foreach ($sales as $row) {
            $handledBy = is_array($row['handledBy'] ?? null) ? HandledBy::fromNode($row['handledBy']) : null;

            if ($handledBy === null) {
                continue;
            }

            $type = is_string($row['type'] ?? null) ? $row['type'] : '';

            $entries[] = new CashierHistoryEntry(
                at: is_string($row['updatedAt'] ?? null) ? $row['updatedAt'] : '',
                who: $handledBy->name,
                decision: 'Accepted',
                subject: 'Order',
                code: is_string($row['code'] ?? null) ? $row['code'] : '—',
                detail: ActivityEntry::saleAction($type),
            );
        }

        return $entries;
    }

    /**
     * Orders whose proof the counter refused — the `rejection` record Reject
     * writes survives on the order until an Approve undo clears it.
     *
     * @return list<CashierHistoryEntry>
     */
    private function rejectedOrders(): array
    {
        $entries = [];

        $orders = $this->client->query(
            'orders',
            RtdbQuery::orderBy('updatedAt')->limitToLast(self::LATEST),
        );

        foreach ($orders as $row) {
            $rejection = $row['rejection'] ?? null;

            if (! is_array($rejection)) {
                continue;
            }

            $by = is_array($rejection['by'] ?? null) ? HandledBy::fromNode($rejection['by']) : null;

            if ($by === null) {
                continue;
            }

            $entries[] = new CashierHistoryEntry(
                at: is_string($rejection['at'] ?? null) ? $rejection['at'] : '',
                who: $by->name,
                decision: 'Rejected',
                subject: 'Order',
                code: is_string($row['code'] ?? null) ? $row['code'] : '—',
                detail: self::claimedDetail($rejection),
            );
        }

        return $entries;
    }

    /**
     * Rental bookings stamped with the counter's decision.
     *
     * @return list<CashierHistoryEntry>
     */
    private function bookings(): array
    {
        $entries = [];

        $bookings = $this->client->query(
            'rentalBookings',
            RtdbQuery::orderBy('updatedAt')->limitToLast(self::LATEST),
        );

        foreach ($bookings as $row) {
            $handledBy = is_array($row['handledBy'] ?? null) ? HandledBy::fromNode($row['handledBy']) : null;

            if ($handledBy === null) {
                continue;
            }

            $status = is_string($row['status'] ?? null) ? RentalStatus::tryFrom($row['status']) : null;

            if ($status === null) {
                continue;
            }

            $decision = self::bookingDecision($status);

            if ($decision === null) {
                continue;
            }

            $entries[] = new CashierHistoryEntry(
                at: self::bookingDecidedAt($row),
                who: $handledBy->name,
                decision: $decision,
                subject: 'Booking',
                code: is_string($row['code'] ?? null) ? $row['code'] : '—',
                detail: $status->label(),
            );
        }

        return $entries;
    }

    /**
     * The counter's verdict on a booking status, or null when no counter
     * decision was ever made (awaiting payment, flagged hold, lapse).
     */
    private static function bookingDecision(RentalStatus $status): ?string
    {
        return match ($status) {
            RentalStatus::Cancelled => 'Rejected',
            RentalStatus::Confirmed,
            RentalStatus::HandedOver,
            RentalStatus::Returned => 'Accepted',
            default => null,
        };
    }

    /**
     * The instant the counter acted on a booking — the stamp its own action
     * wrote, falling back to the node's last update.
     *
     * @param  array<string, mixed>  $row
     */
    private static function bookingDecidedAt(array $row): string
    {
        foreach (['cancelledAt', 'returnedAt', 'handedOverAt', 'verifiedAt', 'updatedAt'] as $field) {
            $value = $row[$field] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * The claimed-vs-total line a rejection records, for the audit column.
     *
     * @param  array<mixed, mixed>  $rejection
     */
    private static function claimedDetail(array $rejection): string
    {
        $claimed = $rejection['enteredAmount'] ?? null;
        $total = $rejection['orderTotal'] ?? null;

        if (! is_numeric($claimed) || ! is_numeric($total)) {
            return 'Payment proof refused';
        }

        return sprintf(
            'Claimed ₱%s of ₱%s',
            number_format((float) $claimed, 2),
            number_format((float) $total, 2),
        );
    }
}
