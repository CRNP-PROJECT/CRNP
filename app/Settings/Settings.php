<?php

declare(strict_types=1);

namespace App\Settings;

/**
 * The diner's business settings, read whole from the single `settings` node.
 *
 * Immutable: every admin edit produces a new instance via a `with*` method, so
 * the write path always persists one coherent node and `RtdbClient` stamps its
 * `updatedAt`. The type is the source of truth for the open/closed computation
 * (`StoreStatus`) and the Complete-setup gate (`hasPaymentBlock`).
 */
final readonly class Settings
{
    /**
     * @param  array<string, Hours>  $hours  keyed by {@see Weekday} value
     * @param  array<string, Closure>  $closures  keyed by a stable closure id
     * @param  array<string, int>  $capacities  keyed by {@see ReservationArea} value
     */
    public function __construct(
        public array $hours = [],
        public bool $forceClose = false,
        public array $closures = [],
        public string $bannerText = '',
        public bool $bannerVisible = false,
        public string $gcashNumber = '',
        public string $gcashQr = '',
        public string $kitchenSecret = '',
        public array $capacities = [],
    ) {}

    public static function defaults(): self
    {
        return new self();
    }

    /**
     * @param  array<mixed, mixed>  $node
     */
    public static function fromNode(array $node): self
    {
        $banner = is_array($node['banner'] ?? null) ? $node['banner'] : [];
        $gcash = is_array($node['gcash'] ?? null) ? $node['gcash'] : [];

        return new self(
            hours: self::hoursFrom($node['hours'] ?? null),
            forceClose: ($node['forceClose'] ?? false) === true,
            closures: self::closuresFrom($node['closures'] ?? null),
            bannerText: self::stringFrom($banner['text'] ?? null),
            bannerVisible: ($banner['visible'] ?? false) === true,
            gcashNumber: self::stringFrom($gcash['number'] ?? null),
            gcashQr: self::stringFrom($gcash['qr'] ?? null),
            kitchenSecret: self::stringFrom($node['kitchenSecret'] ?? null),
            capacities: self::capacitiesFrom($node['capacities'] ?? null),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toNode(): array
    {
        $hours = [];

        foreach ($this->hours as $day => $window) {
            $hours[$day] = $window->toArray();
        }

        $closures = [];

        foreach ($this->closures as $id => $closure) {
            $closures[$id] = $closure->toArray();
        }

        return [
            'hours' => $hours,
            'forceClose' => $this->forceClose,
            'closures' => $closures,
            'banner' => ['text' => $this->bannerText, 'visible' => $this->bannerVisible],
            'gcash' => ['number' => $this->gcashNumber, 'qr' => $this->gcashQr],
            'kitchenSecret' => $this->kitchenSecret,
            'capacities' => $this->capacities,
        ];
    }

    public function hoursFor(Weekday $day): ?Hours
    {
        return $this->hours[$day->value] ?? null;
    }

    public function capacityFor(ReservationArea $area): int
    {
        return $this->capacities[$area->value] ?? $area->defaultCapacity();
    }

    public function hasGcashNumber(): bool
    {
        return trim($this->gcashNumber) !== '';
    }

    public function hasQr(): bool
    {
        return trim($this->gcashQr) !== '';
    }

    /**
     * The Complete-setup gate: ordering opens only once the GCash number *and*
     * the official QR image are both configured — a payment block that does not
     * exist yet can never be sent.
     */
    public function hasPaymentBlock(): bool
    {
        return $this->hasGcashNumber() && $this->hasQr();
    }

    /**
     * @param  array<string, Hours>  $hours
     */
    public function withBusinessHours(array $hours, bool $forceClose): self
    {
        return new self(
            hours: $hours,
            forceClose: $forceClose,
            closures: $this->closures,
            bannerText: $this->bannerText,
            bannerVisible: $this->bannerVisible,
            gcashNumber: $this->gcashNumber,
            gcashQr: $this->gcashQr,
            kitchenSecret: $this->kitchenSecret,
            capacities: $this->capacities,
        );
    }

    public function withBanner(string $text, bool $visible): self
    {
        return new self(
            hours: $this->hours,
            forceClose: $this->forceClose,
            closures: $this->closures,
            bannerText: $text,
            bannerVisible: $visible,
            gcashNumber: $this->gcashNumber,
            gcashQr: $this->gcashQr,
            kitchenSecret: $this->kitchenSecret,
            capacities: $this->capacities,
        );
    }

    /**
     * @param  array<string, int>  $capacities
     */
    public function withCapacities(array $capacities): self
    {
        return new self(
            hours: $this->hours,
            forceClose: $this->forceClose,
            closures: $this->closures,
            bannerText: $this->bannerText,
            bannerVisible: $this->bannerVisible,
            gcashNumber: $this->gcashNumber,
            gcashQr: $this->gcashQr,
            kitchenSecret: $this->kitchenSecret,
            capacities: $capacities,
        );
    }

    public function withClosure(string $id, Closure $closure): self
    {
        return new self(
            hours: $this->hours,
            forceClose: $this->forceClose,
            closures: [...$this->closures, $id => $closure],
            bannerText: $this->bannerText,
            bannerVisible: $this->bannerVisible,
            gcashNumber: $this->gcashNumber,
            gcashQr: $this->gcashQr,
            kitchenSecret: $this->kitchenSecret,
            capacities: $this->capacities,
        );
    }

    public function withoutClosure(string $id): self
    {
        $closures = $this->closures;
        unset($closures[$id]);

        return new self(
            hours: $this->hours,
            forceClose: $this->forceClose,
            closures: $closures,
            bannerText: $this->bannerText,
            bannerVisible: $this->bannerVisible,
            gcashNumber: $this->gcashNumber,
            gcashQr: $this->gcashQr,
            kitchenSecret: $this->kitchenSecret,
            capacities: $this->capacities,
        );
    }

    public function withPayment(string $number, string $qr): self
    {
        return new self(
            hours: $this->hours,
            forceClose: $this->forceClose,
            closures: $this->closures,
            bannerText: $this->bannerText,
            bannerVisible: $this->bannerVisible,
            gcashNumber: $number,
            gcashQr: $qr,
            kitchenSecret: $this->kitchenSecret,
            capacities: $this->capacities,
        );
    }

    public function withKitchenSecret(string $secret): self
    {
        return new self(
            hours: $this->hours,
            forceClose: $this->forceClose,
            closures: $this->closures,
            bannerText: $this->bannerText,
            bannerVisible: $this->bannerVisible,
            gcashNumber: $this->gcashNumber,
            gcashQr: $this->gcashQr,
            kitchenSecret: $secret,
            capacities: $this->capacities,
        );
    }

    /**
     * @return array<string, Hours>
     */
    private static function hoursFrom(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $hours = [];

        foreach (Weekday::cases() as $day) {
            $row = $raw[$day->value] ?? null;

            if (! is_array($row)) {
                continue;
            }

            $window = Hours::fromInput($row['open'] ?? null, $row['close'] ?? null);

            if ($window !== null) {
                $hours[$day->value] = $window;
            }
        }

        return $hours;
    }

    /**
     * @return array<string, Closure>
     */
    private static function closuresFrom(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $closures = [];

        foreach ($raw as $id => $row) {
            if (! is_string($id) || ! is_array($row)) {
                continue;
            }

            $closure = Closure::fromInput($row['from'] ?? null, $row['to'] ?? null, $row['label'] ?? null);

            if ($closure !== null) {
                $closures[$id] = $closure;
            }
        }

        return $closures;
    }

    /**
     * @return array<string, int>
     */
    private static function capacitiesFrom(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $capacities = [];

        foreach (ReservationArea::cases() as $area) {
            $value = $raw[$area->value] ?? null;

            if (is_numeric($value) && (int) $value >= 0) {
                $capacities[$area->value] = (int) $value;
            }
        }

        return $capacities;
    }

    private static function stringFrom(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
