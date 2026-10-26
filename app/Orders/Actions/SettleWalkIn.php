<?php

declare(strict_types=1);

namespace App\Orders\Actions;

use App\Orders\HandledBy;
use App\Orders\LineItem;
use App\Orders\Orders;
use App\Orders\OrderStatus;
use App\Orders\Payment;
use App\Orders\SplitTender;
use App\Orders\TenderMethod;
use App\Rtdb\Contracts\RtdbClient;
use App\Stats\Actions\TopThreeRanking;
use InvalidArgumentException;

/**
 * Settles a walk-in: derives the tender from the cashier's payment choice,
 * asserts it sums to the total, then writes the order and its sale row.
 *
 * `Cash` → all-cash with computed change and no GCash leg; `GCash` → one
 * GCash leg for the typed amount, which must equal the total; `Split` → the
 * typed cash and GCash legs, which must sum to the total, change `0`, and a
 * ref# is required because the GCash leg is `> 0` (PLAN §4 *Cashier POS*). At
 * settle the order flips to `cooking`, so the kitchen ticket appears the same
 * instant and not before.
 *
 * The status guard makes a stale or duplicate settle a friendly no-op. The
 * `sales/{id}` row is keyed by the same order id (1:1 with the order), so a
 * second sale is structurally impossible, and both rows carry `handledBy`.
 *
 * The sale write also recomputes the **Top 3** ranking (#31) eagerly.
 */
final readonly class SettleWalkIn
{
    public function __construct(
        private RtdbClient $client,
        private Orders $orders,
        private TopThreeRanking $ranking,
    ) {}

    public function __invoke(
        string $orderId,
        TenderMethod $method,
        float $cashTyped,
        float $gcashTyped,
        ?string $gcashRef,
        HandledBy $handledBy,
    ): void {
        $order = $this->orders->require($orderId);

        // Status-guarded: a stale or duplicate tap never flips a settled order.
        if ($order->status !== OrderStatus::Pending) {
            return;
        }

        $tender = SplitTender::for($method, $order->total, $cashTyped, $gcashTyped);
        $gcashRef = $gcashRef !== null ? trim($gcashRef) : null;

        if ($tender->requiresGcashRef && ($gcashRef === null || $gcashRef === '')) {
            throw new InvalidArgumentException('A GCash reference number is required for the GCash leg.');
        }

        // True by construction, still validated: the tender can never drift.
        SplitTender::assertBalanced($tender->payments, $order->total);

        $settledAt = now('UTC')->toIso8601ZuluString();
        $payments = array_map(static fn(Payment $payment): array => $payment->toNode(), $tender->payments);

        $this->client->update("orders/{$orderId}", [
            'status' => OrderStatus::Cooking->value,
            'payments' => $payments,
            'change' => $tender->change,
            'gcashRef' => $tender->requiresGcashRef ? $gcashRef : null,
            'settledAt' => $settledAt,
            'handledBy' => $handledBy->toNode(),
        ]);

        // `sales/{id}` is 1:1 with the order key — the shared key makes a second
        // sale structurally impossible. `lines` ride along so the Top 3 ranking
        // (#31) can tally units per product without re-reading the order, and
        // `serviceType` is snapshotted so the sales report splits dine-in from
        // take-out without re-reading the order either.
        $this->client->set("sales/{$orderId}", [
            'code' => $order->code,
            'type' => $order->type,
            'serviceType' => $order->serviceType?->value,
            'lines' => array_map(static fn(LineItem $line): array => $line->toNode(), $order->lines),
            'total' => $order->total,
            'payments' => $payments,
            'soldAt' => $settledAt,
            'handledBy' => $handledBy->toNode(),
        ]);

        // Recompute Top 3 eagerly over the rolling window on this sale write.
        ($this->ranking)();
    }
}
