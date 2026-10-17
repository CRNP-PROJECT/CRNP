<?php

declare(strict_types=1);

namespace App\Kitchen;

use App\Cache\KitchenBoardCache;
use App\Orders\Order;
use App\Orders\Orders;
use App\Orders\OrderStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The **kitchen board** — the server-owned NOW/LATER read behind `/kitchen`
 * (PLAN §4 *Kitchen board*).
 *
 * The cook owns nothing: this object decides which lane a ticket is in, its age
 * timer, whether it is red, whether it wears the NEW badge, whether it reads
 * LATE, and when it expires. The board mirrors **verified, unserved** orders
 * only (`orders/.indexOn status` = `cooking`), so a walk-in appears at settle,
 * never at the first tile tap, and Mark served or a Reject drops it the same
 * instant.
 *
 * Two clocks ride on every ticket:
 *
 * - **`cookableAt`** — the age origin. It is deterministic, so a kiosk that
 *   slept and reloads late still counts the ticket's real age rather than
 *   resetting to 0:00:
 *     - walk-in: `settledAt`
 *     - ASAP online: `verifiedAt`
 *     - scheduled: `max(pickupAt − cook lead, verifiedAt)` — the promotion
 *       instant.
 * - **`readyFor`** — when it should be cooked and waiting:
 *     - scheduled: `pickupAt`
 *     - ASAP/walk-in: `cookableAt + cook lead`.
 *
 * **NOW** is the tickets whose `cookableAt` has arrived, oldest-first by age;
 * **LATER** is the scheduled pickups still inside the cook lead, sorted by
 * ready-for. A ticket expires (clears off the board) at `readyFor + 15 min`, or
 * earlier when Mark served / Reject flips its status — the close sweep's
 * `Unclaimed` write clears it the same way.
 *
 * **All-day counts are over NOW only**: a cook must never be told to make LATER
 * food early.
 *
 * The read is one status-bounded query, served from the coalesced
 * {@see KitchenBoardCache} entry, so N kiosks cost one RTDB read per poll
 * interval.
 */
final readonly class KitchenBoard
{
    /**
     * The poll cadence the board fetches on, and the coalesced cache TTL — the
     * entry is never older than the cadence the client already accepts.
     */
    public const POLL_SECONDS = 5;

    /**
     * A ticket's age timer reads red once it has been cookable this long.
     */
    public const RED_AFTER_SECONDS = 12 * 60;

    /**
     * The NEW badge is genuine for this long after a ticket becomes cookable —
     * the client flashes it for the first ~5 s, then holds it steady until here.
     */
    public const NEW_FOR_SECONDS = 3 * 60;

    /**
     * A ticket clears off the board this long after its ready-for time, unless
     * Mark served or a Reject removes it sooner (or the close sweep does).
     */
    public const CLEAR_AFTER_SECONDS = 15 * 60;

    public function __construct(private Orders $orders) {}

    public function snapshot(?CarbonInterface $now = null): KitchenSnapshot
    {
        $at = CarbonImmutable::instance($now ?? now('UTC'));

        // The board mirrors the orders in `cooking` — the one status whose
        // tickets are cookable. The read routes through the shared order seam,
        // so the rule lives with every other status read, not here.
        $orders = $this->orders->byStatus(OrderStatus::Cooking);

        $nowTickets = [];
        $laterTickets = [];

        foreach ($orders as $order) {
            $ticket = $this->ticket($order, $at);

            if ($ticket === null) {
                continue;
            }

            if ($ticket->lane === KitchenSnapshot::LANE_NOW) {
                $nowTickets[] = $ticket;
            } else {
                $laterTickets[] = $ticket;
            }
        }

        // NOW: oldest-first by age (earliest cookableAt), ties broken by id so
        // the order is stable. LATER: by ready-for, the soonest pickup first.
        usort($nowTickets, static function (KitchenTicket $a, KitchenTicket $b): int {
            return [$a->cookableAt, $a->id] <=> [$b->cookableAt, $b->id];
        });

        usort($laterTickets, static function (KitchenTicket $a, KitchenTicket $b): int {
            return [$a->readyFor, $a->id] <=> [$b->readyFor, $b->id];
        });

        return new KitchenSnapshot($nowTickets, $laterTickets, self::allDay($nowTickets));
    }

    /**
     * Project one order to a ticket, or null when it has expired off the board.
     */
    private function ticket(Order $order, CarbonImmutable $at): ?KitchenTicket
    {
        $cookableAt = $this->cookableAt($order);
        $readyFor = $this->readyFor($order, $cookableAt);

        // Expiry: ready-for + 15 min. A ticket still inside the lead is never
        // expired, and Mark served / Reject / the close sweep clear by status.
        if ($at->getTimestamp() > $readyFor->getTimestamp() + self::CLEAR_AFTER_SECONDS) {
            return null;
        }

        // A promoted scheduled ticket never reuses the NEW flash — the server
        // marks it by its pickupAt instead.
        $promoted = $order->pickupAt !== null;
        $inNow = $at->getTimestamp() >= $cookableAt->getTimestamp();
        $age = $inNow ? $at->getTimestamp() - $cookableAt->getTimestamp() : 0;

        return new KitchenTicket(
            id: $order->id,
            code: $order->code,
            type: $order->type,
            lane: $inNow ? KitchenSnapshot::LANE_NOW : KitchenSnapshot::LANE_LATER,
            readyFor: $readyFor->toIso8601ZuluString(),
            cookableAt: $cookableAt->toIso8601ZuluString(),
            ageSeconds: $age,
            promoted: $promoted,
            late: $inNow && $at->getTimestamp() > $readyFor->getTimestamp(),
            red: $inNow && $age >= self::RED_AFTER_SECONDS,
            new: $inNow && ! $promoted && $age < self::NEW_FOR_SECONDS,
            lines: $order->lines,
        );
    }

    /**
     * The deterministic age origin: the instant the ticket became cookable.
     */
    private function cookableAt(Order $order): CarbonImmutable
    {
        $verified = self::instant($order->verifiedAt)
            ?? self::instant($order->settledAt)
            ?? self::instant($order->placedAt)
            ?? CarbonImmutable::now('UTC');

        if ($order->pickupAt === null) {
            return $verified;
        }

        // Scheduled: the server promotes at `pickup − cook lead` or at verify,
        // whichever is later.
        $pickup = self::instant($order->pickupAt);

        if ($pickup === null) {
            return $verified;
        }

        $floor = CookLead::before($pickup);

        return $verified->greaterThan($floor) ? $verified : $floor;
    }

    /**
     * When the ticket should be cooked and waiting.
     */
    private function readyFor(Order $order, CarbonImmutable $cookableAt): CarbonImmutable
    {
        if ($order->pickupAt !== null) {
            return self::instant($order->pickupAt) ?? $cookableAt;
        }

        return CookLead::after($cookableAt);
    }

    /**
     * All-day quantities, summed over NOW only and keyed by line name.
     *
     * @param  list<KitchenTicket>  $nowTickets
     * @return list<array{name: string, quantity: int}>
     */
    private static function allDay(array $nowTickets): array
    {
        $totals = [];

        foreach ($nowTickets as $ticket) {
            foreach ($ticket->lines as $line) {
                $totals[$line->name] = ($totals[$line->name] ?? 0) + $line->quantity;
            }
        }

        ksort($totals);

        $rows = [];

        foreach ($totals as $name => $quantity) {
            $rows[] = ['name' => (string) $name, 'quantity' => $quantity];
        }

        return $rows;
    }

    private static function instant(?string $iso): ?CarbonImmutable
    {
        if ($iso === null || $iso === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($iso);
        } catch (\Throwable) {
            return null;
        }
    }
}
