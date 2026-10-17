<?php

declare(strict_types=1);

namespace App\Settings;

/**
 * A scheduled date-range closure — a holiday configured once that flips the
 * site closed by itself. Dates are inclusive `Y-m-d` wall-clock strings.
 */
final readonly class Closure
{
    public function __construct(
        public string $from,
        public string $to,
        public string $label,
    ) {}

    /**
     * Build from raw form input, or null when the range is malformed.
     */
    public static function fromInput(mixed $from, mixed $to, mixed $label): ?self
    {
        $from = is_string($from) ? trim($from) : '';
        $to = is_string($to) ? trim($to) : '';

        if (! self::isDate($from) || ! self::isDate($to) || $to < $from) {
            return null;
        }

        $label = is_string($label) ? trim($label) : '';

        return new self($from, $to, $label !== '' ? $label : 'Closed');
    }

    public function covers(string $date): bool
    {
        return $date >= $this->from && $date <= $this->to;
    }

    /**
     * @return array{from: string, to: string, label: string}
     */
    public function toArray(): array
    {
        return ['from' => $this->from, 'to' => $this->to, 'label' => $this->label];
    }

    private static function isDate(string $value): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
    }
}
