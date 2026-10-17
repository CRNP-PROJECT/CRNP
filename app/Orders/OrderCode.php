<?php

declare(strict_types=1);

namespace App\Orders;

/**
 * The short code identifying an order at the counter (CONTEXT — *Order code*).
 *
 * One code space is shared by online orders and walk-ins: online orders draw
 * one at placement, walk-ins at ring-up. It is drawn, never typed, and printed
 * on the receipt and kitchen ticket.
 *
 * The alphabet drops the glyphs a human confuses (0/O, 1/I/L) so a code read
 * aloud or off a receipt is unambiguous.
 */
final readonly class OrderCode
{
    private const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    private const LENGTH = 4;

    private function __construct(public string $value) {}

    /**
     * Draw a fresh code from the shared alphabet.
     */
    public static function draw(): self
    {
        $alphabet = self::ALPHABET;
        $max = strlen($alphabet) - 1;
        $code = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= $alphabet[random_int(0, $max)];
        }

        return new self($code);
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->value;
    }
}
