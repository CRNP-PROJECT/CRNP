<?php

declare(strict_types=1);

namespace App\Orders;

use App\Rtdb\Contracts\RtdbClient;
use App\Stats\Actions\TopThreeRanking;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The **close** sweep (PLAN §4 *Data layer* — *Close*; CONTEXT — *Unclaimed*).
 *
 * Runs at the weekday closing hour (and on force-close). A paid, cooked order
 * the customer never collected is decided **Unclaimed** — money kept, counted as
 * revenue — and the close sweep **synthesizes the Unclaimed `sales/{id}` row**
 * (the 1:1 key with the order, the same key *Mark served* writes), so a later
 * *Collect late* (#29) cannot write a second one.
 *
 * The status flip to `Unclaimed` is what **clears the server-owned rows**: the
 * kitchen board reads `cooking` and the cashier queue reads the payment states,
 * so both drop the ticket with no extra write and no dependence on the board's
 * timer (the counter's ready-awaiting-handover row is #26/#28's read). The write
 * also stamps `settledAt` — an Unclaimed order never settles through Mark
 * served — so the daily proof-cleanup sweep expires its screenshot 7 days on.
 *
 * Only orders that were already paid and cooking are touched. A **Rejected**
 * order is left alone — its refund obligation is #27's invariant and the owner
 * finds it in the morning. A **Dismissed** order was never paid and is #29's
 * Restore/Void concern, and a `Served` order already has its sale.
 *
 * The sweep is idempotent: a second run (or a force-close re-run) finds the
 * order no longer `Cooking` and writes nothing, and it never overwrites a sale a
 * settled walk-in already wrote. Each synthesized sale recomputes the **Top 3**
 * ranking (#31) once for the run, so a close-time Unclaimed sale lands in the
 * chip without a per-order recompute.
 */
final readonly class CloseSweep
{
    public function __construct(
        private RtdbClient $client,
        private Orders $orders,
        private TopThreeRanking $ranking,
    ) {}

    /**
     * Decide Unclaimed for every still-uncollected paid order and synthesize its
     * sale once.
     *
     * @return list<string> the order ids decided Unclaimed
     */
    public function __invoke(?CarbonInterface $now = null): array
    {
        $at = CarbonImmutable::instance($now ?? now('UTC'))->toIso8601ZuluString();

        $unclaimed = [];

        foreach ($this->orders->byStatus(OrderStatus::Cooking) as $order) {
            $this->client->update("orders/{$order->id}", [
                'status' => OrderStatus::Unclaimed->value,
                'unclaimedAt' => $at,
                // The terminal instant the daily proof-cleanup sweep keys off
                // to expire the screenshot 7 days on. A walk-in already stamped
                // its counter `settledAt` (the receipt prints it), so keep it;
                // only an order that never settled gets the close instant.
                'settledAt' => $order->settledAt ?? $at,
            ]);

            // The sale is 1:1 with the order key; a walk-in settled its own at
            // the counter, so only synthesize when none exists yet.
            if ($this->client->get("sales/{$order->id}") === null) {
                $this->client->set("sales/{$order->id}", $this->sale($order, $at));
            }

            $unclaimed[] = $order->id;
        }

        // Recompute Top 3 once for the run, after any synthesized sales are in
        // place, so a close-time Unclaimed sale lands in the chip (#31).
        if ($unclaimed !== []) {
            ($this->ranking)();
        }

        return $unclaimed;
    }

    /**
     * The Unclaimed sale row — the same shape *Mark served* writes
     * ({@see \App\Orders\Actions\SettleWalkIn}), with no `handledBy` because no
     * cashier acted: the close sweep did.
     *
     * @return array<string, mixed>
     */
    private function sale(Order $order, string $at): array
    {
        return [
            'code' => $order->code,
            'type' => $order->type,
            'lines' => array_map(static fn(LineItem $line): array => $line->toNode(), $order->lines),
            'total' => $order->total,
            'payments' => array_map(static fn(Payment $payment): array => $payment->toNode(), $order->payments),
            'soldAt' => $at,
        ];
    }
}
