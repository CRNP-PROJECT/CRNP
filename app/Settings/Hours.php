<?php

declare(strict_types=1);

namespace App\Settings;

use Carbon\CarbonInterface;

/**
 * One weekday's opening window, held as `HH:MM` wall-clock strings.
 *
 * The window is half-open — `[open, close)` — so the store is open at the
 * opening minute and closed at the closing minute. A window whose close is not
 * after its open is treated as closed for the whole day.
 */
final readonly class Hours
{
    public function __construct(
        public string $open,
        public string $close,
    ) {}

    /**
     * Build from raw form input, or null when the day is closed / malformed.
     */
    public static function fromInput(mixed $open, mixed $close): ?self
    {
        $open = is_string($open) ? trim($open) : '';
        $close = is_string($close) ? trim($close) : '';

        if ($open === '' || $close === '') {
            return null;
        }

        if (! self::isClockTime($open) || ! self::isClockTime($close)) {
            return null;
        }

        if ($open >= $close) {
            return null;
        }

        return new self($open, $close);
    }

    public function contains(CarbonInterface $at): bool
    {
        $minutes = (int) $at->format('G') * 60 + (int) $at->format('i');

        return $minutes >= $this->openMinutes() && $minutes < $this->closeMinutes();
    }

    /**
     * @return array{open: string, close: string}
     */
    public function toArray(): array
    {
        return ['open' => $this->open, 'close' => $this->close];
    }

    private function openMinutes(): int
    {
        return (int) substr($this->open, 0, 2) * 60 + (int) substr($this->open, 3, 2);
    }

    private function closeMinutes(): int
    {
        return (int) substr($this->close, 0, 2) * 60 + (int) substr($this->close, 3, 2);
    }

    private static function isClockTime(string $value): bool
    {
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) === 1;
    }
}
