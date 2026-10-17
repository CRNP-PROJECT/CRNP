<?php

declare(strict_types=1);

namespace App\Rentals;

/**
 * The short code identifying a rental booking at the counter.
 *
 * Rentals are a separate stream from orders, so their codes draw from their own
 * space (a leading `R`) — a code read at the counter is unambiguously a rental,
 * never an order. The alphabet drops the glyphs a human confuses (0/O, 1/I/L).
 */
final readonly class RentalCode
{
    private const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    private const LENGTH = 4;

    private function __construct(public string $value) {}

    public static function draw(): self
    {
        $alphabet = self::ALPHABET;
        $max = strlen($alphabet) - 1;
        $code = 'R';

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
