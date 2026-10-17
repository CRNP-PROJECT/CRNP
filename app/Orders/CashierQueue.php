<?php

declare(strict_types=1);

namespace App\Orders;

use Carbon\CarbonImmutable;

/**
 * The cashier's **online pickup queue** (PLAN §4 *Cashier POS*).
 *
 * Prepaid online orders arrive **ready-to-pay** with no approval tap — the QR
 * went out at placement and the screenshot auto-verifies — and the same
 * verified, unserved orders form the **recently-verified list** for at-leisure
 * spot-check, where Reject stays reachable until Mark served. In-window orders
 * with **no screenshot yet** show as **awaiting proof**, so staff see a customer
 * waiting rather than a blank queue.
 *
 * A **flagged hold** (`verifying`) is the only thing that interrupts
 * auto-verify: the entered amount or the ref# mismatched, so both numbers are
 * shown side by side for one-tap **Approve / Reject**. A **rejected** order is
 * its own section — the only actions are **Approve** (the undo) or **Void**,
 * never Restore — carrying the claimed refund beside the order total.
 *
 * The read is status-bounded queries (`orders/.indexOn status`), and each row
 * is a scalar projection of `orders/{id}` — the proof image lives in the sibling
 * `proofs/{id}` node and never rides here, so the queue is scalar by
 * construction (PLAN §4 *Quota discipline*, rule 3). Only online orders are in
 * scope: a walk-in is rung up and handed over at the counter, never through the
 * online queue.
 *
 * #29 adds three sections: the **ready — awaiting handover** row for tickets
 * the server cleared off the board (paid, verified, unserved, past ready-for +
 * grace), the **Dismissed** list (window lapsed with zero proof; Restore /
 * Void), and the **Unclaimed** list (paid, never collected; Collect late).
 *
 * The computed snapshot is served from a coalesced cache entry whose TTL equals
 * {@see self::POLL_SECONDS} (rule 1), so N registers cost one RTDB read per
 * interval; every cashier tap evicts the entry so the cashier's own tap
 * propagates on the next request rather than after the TTL.
 */
final readonly class CashierQueue
{
    /**
     * The poll cadence the queue screen fetches on, and the coalesced cache TTL
     * — the entry is never older than the cadence the client already accepts.
     */
    public const POLL_SECONDS = 5;

    public function __construct(private Orders $orders) {}

    public function snapshot(): QueueSnapshot
    {
        $at = CarbonImmutable::instance(now('UTC'));
        $cooking = $this->verifiedUnserved($at);

        return new QueueSnapshot(
            flaggedHolds: $this->flaggedHolds(),
            readyToPay: $cooking['readyToPay'],
            recentlyVerified: $cooking['readyToPay'],
            awaitingProof: $this->awaitingProof($at),
            awaitingHandover: $cooking['awaitingHandover'],
            dismissed: $this->retiredByStatus(OrderStatus::Dismissed),
            rejected: $this->rejected(),
            unclaimed: $this->retiredByStatus(OrderStatus::Unclaimed),
        );
    }

    /**
     * Orders parked on a flagged hold — entered amount ≠ total or a blank ref#.
     * The kitchen waits on the cashier's one-tap Approve / Reject.
     *
     * @return list<QueueOrder>
     */
    private function flaggedHolds(): array
    {
        $orders = $this->online($this->orders->byStatus(OrderStatus::Verifying));

        usort($orders, static fn(Order $a, Order $b): int => self::moment($a->flaggedAt) <=> self::moment($b->flaggedAt));

        return array_map(static fn(Order $order): QueueOrder => QueueOrder::fromOrder($order), $orders);
    }

    /**
     * Prepaid, verified, unserved online orders — the ready-to-pay and
     * recently-verified sections share this set, minus the tickets the server has
     * already cleared off the board into the handover row. Auto-verification is
     * what puts an order here, so there is no approval tap; Mark served is the
     * one happy-path tap off it.
     *
     * @return array{readyToPay: list<QueueOrder>, awaitingHandover: list<QueueOrder>}
     */
    private function verifiedUnserved(CarbonImmutable $at): array
    {
        $orders = $this->online($this->orders->byStatus(OrderStatus::Cooking));

        $ready = [];
        $handover = [];

        foreach ($orders as $order) {
            if (ReadyFor::isAwaitingHandover($order, $at)) {
                $handover[] = $order;
            } else {
                $ready[] = $order;
            }
        }

        usort($ready, static fn(Order $a, Order $b): int => self::moment($a->placedAt) <=> self::moment($b->placedAt));
        usort($handover, static fn(Order $a, Order $b): int => self::moment($a->placedAt) <=> self::moment($b->placedAt));

        return [
            'readyToPay' => array_map(static fn(Order $order): QueueOrder => QueueOrder::fromOrder($order), $ready),
            'awaitingHandover' => array_map(static fn(Order $order): QueueOrder => QueueOrder::fromOrder($order), $handover),
        ];
    }

    /**
     * The online orders in one retired state — Dismissed (Restore / Void) or
     * Unclaimed (Collect late) — keyed by the order code's placement age so the
     * oldest sits first.
     *
     * @return list<QueueOrder>
     */
    private function retiredByStatus(OrderStatus $status): array
    {
        $orders = $this->online($this->orders->byStatus($status));

        usort($orders, static fn(Order $a, Order $b): int => self::moment($a->placedAt) <=> self::moment($b->placedAt));

        return array_map(static fn(Order $order): QueueOrder => QueueOrder::fromOrder($order), $orders);
    }

    /**
     * In-window online orders with no screenshot yet — a customer mid-payment,
     * not a blank queue. A lapsed window (the expiry sweep's concern) or an
     * order that already has a proof drops out.
     *
     * @return list<QueueOrder>
     */
    private function awaitingProof(CarbonImmutable $at): array
    {
        $orders = $this->online($this->orders->byStatus(OrderStatus::AwaitingPayment));

        $awaiting = [];

        foreach ($orders as $order) {
            if ($order->proofReceivedAt !== null || $order->deadlineAt === null) {
                continue;
            }

            if (CarbonImmutable::parse($order->deadlineAt)->greaterThan($at)) {
                $awaiting[] = $order;
            }
        }

        usort($awaiting, static fn(Order $a, Order $b): int => self::moment($a->deadlineAt) <=> self::moment($b->deadlineAt));

        return array_map(static fn(Order $order): QueueOrder => QueueOrder::fromOrder($order), $awaiting);
    }

    /**
     * Rejected online orders — its own state. The only actions are Approve (the
     * undo) or Void, never Restore; the claimed refund rides beside the total.
     *
     * @return list<QueueOrder>
     */
    private function rejected(): array
    {
        $orders = $this->online($this->orders->byStatus(OrderStatus::Rejected));

        usort($orders, static fn(Order $a, Order $b): int => self::moment($a->placedAt) <=> self::moment($b->placedAt));

        return array_map(static fn(Order $order): QueueOrder => QueueOrder::fromOrder($order), $orders);
    }

    /**
     * The online orders of a status read, dropping a row that belongs to the
     * walk-in stream — a walk-in is rung up and handed over at the counter,
     * never through the online queue.
     *
     * @param  array<string, Order>  $orders
     * @return list<Order>
     */
    private function online(array $orders): array
    {
        $online = [];

        foreach ($orders as $order) {
            if ($order->type === OrderType::Online->value) {
                $online[] = $order;
            }
        }

        return $online;
    }

    /**
     * A sortable instant for an ISO-8601 string, with a missing one sorted last.
     */
    private static function moment(?string $iso): int
    {
        return $iso !== null ? CarbonImmutable::parse($iso)->getTimestamp() : PHP_INT_MAX;
    }
}
