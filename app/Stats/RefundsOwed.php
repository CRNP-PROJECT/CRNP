<?php

declare(strict_types=1);

namespace App\Stats;

use App\Orders\OrderStatus;
use App\Orders\Orders;

/**
 * Reads the outstanding **refunds owed** (PLAN §4 *Admin* — *Dashboard* /
 * *Reports*; CONTEXT — *Refund owed*).
 *
 * An obligation is booked as `refundOwed` when a proof is rejected (#27) and
 * cleared only by the admin's **Mark refunded**. Because a rejection is the only
 * booking and Approve (the undo) the only other clearing path, every outstanding
 * obligation sits on an order in the `rejected` or `voided` state — Void never
 * clears it. The read is therefore two **status-bounded** reads through
 * {@see Orders::byStatus()} (rule 3), not a whole-tree scan, and each row is
 * filtered to those still carrying a positive `refundOwed`.
 */
final readonly class RefundsOwed
{
    public function __construct(private Orders $orders) {}

    /**
     * The outstanding obligations, newest rejection first.
     *
     * @return list<array{orderId: string, code: string, refundOwed: float, orderTotal: float, rejectedAt: ?string}>
     */
    public function all(): array
    {
        $rows = [];

        foreach ([OrderStatus::Rejected, OrderStatus::Voided] as $status) {
            foreach ($this->orders->byStatus($status) as $order) {
                if ($order->refundOwed === null || $order->refundOwed <= 0) {
                    continue;
                }

                $rows[] = [
                    'orderId' => $order->id,
                    'code' => $order->code,
                    'refundOwed' => round($order->refundOwed, 2),
                    'orderTotal' => round($order->total, 2),
                    'rejectedAt' => $order->rejectedAt,
                ];
            }
        }

        usort($rows, static function (array $a, array $b): int {
            return strcmp((string) $b['rejectedAt'], (string) $a['rejectedAt']);
        });

        return $rows;
    }
}
