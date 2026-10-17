<?php

declare(strict_types=1);

namespace App\Kitchen;

use App\Catalog\AddOn;
use App\Orders\LineItem;

/**
 * One ticket on the kitchen board (PLAN §4 *Kitchen board* — read-only).
 *
 * The board shows **verified, unserved** orders only, and the server owns
 * every decision: which lane a ticket is in (NOW or LATER), whether it is
 * red (aged past 12 min), whether it wears the NEW badge (a *genuine* new
 * ticket, not a promoted one), and whether it reads LATE (overdue). The cook
 * owns nothing — this object is the scalar projection the board renders.
 *
 * Two clocks ride on every ticket: `cookableAt` (the age origin — when it
 * entered the kitchen set) and `readyFor` (when it should be cooked and
 * waiting). A **promoted** scheduled ticket shows both; an ASAP ticket derives
 * `readyFor` from `cookableAt`.
 */
final readonly class KitchenTicket
{
    /**
     * @param  list<LineItem>  $lines
     */
    public function __construct(
        public string $id,
        public string $code,
        public string $type,
        public string $lane,
        public string $readyFor,
        public string $cookableAt,
        public int $ageSeconds,
        public bool $promoted,
        public bool $late,
        public bool $red,
        public bool $new,
        public array $lines,
    ) {}

    /**
     * Whether the ticket is in NOW.
     */
    public function isNow(): bool
    {
        return $this->lane === KitchenSnapshot::LANE_NOW;
    }

    /**
     * The age timer as `M:SS`, clamped at zero.
     */
    public function ageClock(): string
    {
        return self::clock($this->ageSeconds);
    }

    /**
     * The seconds until ready-for, clamped at zero — the LATER countdown.
     */
    public function readyInSeconds(int $nowSeconds): int
    {
        return max(0, self::timestamp($this->readyFor) - $nowSeconds);
    }

    /**
     * A countdown as `M:SS`, clamped at zero.
     */
    public static function clock(int $seconds): string
    {
        $seconds = max(0, $seconds);

        return intdiv($seconds, 60) . ':' . str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT);
    }

    private static function timestamp(string $iso): int
    {
        return (int) strtotime($iso);
    }

    /**
     * The shape the board's state endpoint returns — scalar by construction,
     * the proof image never rides here.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'type' => $this->type,
            'lane' => $this->lane,
            'readyFor' => $this->readyFor,
            'cookableAt' => $this->cookableAt,
            'ageSeconds' => $this->ageSeconds,
            'promoted' => $this->promoted,
            'late' => $this->late,
            'red' => $this->red,
            'new' => $this->new,
            'lines' => array_map(
                static fn(LineItem $line): array => [
                    'name' => $line->name,
                    'quantity' => $line->quantity,
                    'addOns' => array_map(
                        static fn(AddOn $addOn): array => ['name' => $addOn->name],
                        $line->addOns,
                    ),
                ],
                $this->lines,
            ),
        ];
    }
}
