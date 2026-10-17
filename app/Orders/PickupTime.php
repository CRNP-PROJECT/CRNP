<?php

declare(strict_types=1);

namespace App\Orders;

use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * A customer's pickup time for an online order (CONTEXT — *Pickup time*).
 *
 * The default is **ASAP** (no pickup time), and the earliest a customer may
 * choose is **now + 15 minutes** — the picker's floor and the server-side
 * validation share this one definition, so no error can follow payment. A
 * scheduled pickup is stored as a UTC ISO instant, the same clock the rest of
 * the order carries.
 *
 * Wall-clock input arrives in the business timezone (`Asia/Manila`), so the
 * boundary is evaluated there regardless of the server's UTC clock.
 */
final readonly class PickupTime
{
    /**
     * The head start a customer must leave before a scheduled pickup.
     */
    public const LEAD_MINUTES = 15;

    private function __construct(public ?CarbonImmutable $at) {}

    public static function asap(): self
    {
        return new self(null);
    }

    /**
     * The earliest instant a customer may schedule for, from the given "now".
     */
    public static function earliest(DateTimeInterface $now): CarbonImmutable
    {
        return CarbonImmutable::instance($now)->addMinutes(self::LEAD_MINUTES);
    }

    /**
     * Build from the picker's input — an empty value (or "asap") means ASAP.
     *
     * @throws InvalidArgumentException when the chosen time is before the
     *                                  earliest selectable instant.
     */
    public static function fromInput(mixed $input, DateTimeInterface $now): self
    {
        if ($input === null || ! is_string($input) || trim($input) === ''
            || strtolower(trim($input)) === 'asap') {
            return self::asap();
        }

        try {
            $at = CarbonImmutable::parse(trim($input), BusinessTime::timezone());
        } catch (\Throwable) {
            throw new InvalidArgumentException('That pickup time could not be read.');
        }

        if ($at->lessThan(self::earliest($now))) {
            throw new InvalidArgumentException(
                'The earliest pickup time is ' . self::LEAD_MINUTES . ' minutes from now.',
            );
        }

        return new self($at);
    }

    public function isAsap(): bool
    {
        return $this->at === null;
    }

    /**
     * The pickup instant for the order node — null for ASAP.
     */
    public function toNode(): ?string
    {
        return $this->at?->utc()->toIso8601ZuluString();
    }

    /**
     * The value the picker's `min` attribute carries (local wall-clock), as a
     * `datetime-local` value (`YYYY-MM-DDTHH:MM`).
     */
    public static function minInput(DateTimeInterface $now): string
    {
        return self::earliest($now)->setTimezone(BusinessTime::timezone())->format('Y-m-d\TH:i');
    }
}
