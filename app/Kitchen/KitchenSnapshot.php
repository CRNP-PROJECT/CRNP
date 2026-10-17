<?php

declare(strict_types=1);

namespace App\Kitchen;

/**
 * The kitchen board's read model: the NOW and LATER lanes plus the all-day
 * counts (PLAN §4 *Kitchen board*).
 *
 * - **NOW** — oldest-first tickets the kitchen may cook now.
 * - **LATER** — scheduled pickups still inside the cook lead, dimmed, sorted by
 *   ready-for.
 * - **all-day** — quantities summed over **NOW only**, so a cook is never told
 *   to make LATER food early.
 *
 * The `signature` changes whenever a lane's membership or a ticket's flags do,
 * so the polling kiosk reloads the server-rendered board only when something
 * actually moved. Every field is scalar — the proof image never rides here.
 */
final readonly class KitchenSnapshot
{
    public const LANE_NOW = 'now';

    public const LANE_LATER = 'later';

    /**
     * @param  list<KitchenTicket>  $now
     * @param  list<KitchenTicket>  $later
     * @param  list<array{name: string, quantity: int}>  $allDay
     */
    public function __construct(
        public array $now,
        public array $later,
        public array $allDay,
    ) {}

    public function signature(): string
    {
        $now = self::laneSignature($this->now);
        $later = self::laneSignature($this->later);

        $allDay = [];

        foreach ($this->allDay as $row) {
            $allDay[] = $row['name'] . 'x' . $row['quantity'];
        }

        return 'now[' . $now . ']later[' . $later . ']all[' . implode(',', $allDay) . ']';
    }

    /**
     * @param  list<KitchenTicket>  $tickets
     */
    private static function laneSignature(array $tickets): string
    {
        $parts = [];

        foreach ($tickets as $ticket) {
            $parts[] = $ticket->id
                . ':' . ($ticket->red ? 'r' : '-')
                . ($ticket->new ? 'n' : '-')
                . ($ticket->late ? 'l' : '-');
        }

        return implode(',', $parts);
    }

    /**
     * The lane counts the board header shows.
     *
     * @return array{now: int, later: int}
     */
    public function counts(): array
    {
        return ['now' => count($this->now), 'later' => count($this->later)];
    }

    /**
     * @return array{
     *     now: list<array<string, mixed>>,
     *     later: list<array<string, mixed>>,
     *     allDay: list<array{name: string, quantity: int}>,
     *     counts: array{now: int, later: int},
     *     signature: string
     * }
     */
    public function toArray(): array
    {
        return [
            'now' => array_map(static fn(KitchenTicket $ticket): array => $ticket->toArray(), $this->now),
            'later' => array_map(static fn(KitchenTicket $ticket): array => $ticket->toArray(), $this->later),
            'allDay' => $this->allDay,
            'counts' => [
                'now' => count($this->now),
                'later' => count($this->later),
            ],
            'signature' => $this->signature(),
        ];
    }
}
