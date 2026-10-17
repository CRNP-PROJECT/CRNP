<?php

declare(strict_types=1);

namespace App\Settings\Actions;

use App\Cache\AvailabilityCache;
use App\Settings\ReservationArea;
use App\Settings\SettingsRepository;

/**
 * Persists reservation capacities per area — the smart defaults feeding the
 * public occupancy view. An area left blank keeps its default.
 *
 * The write evicts the availability cache (PLAN §4 *Quota discipline*, rule 2),
 * so the public occupancy view recomputes against the new capacities on the
 * next request rather than after the TTL backstop.
 */
final readonly class UpdateCapacities
{
    public function __construct(
        private SettingsRepository $settings,
        private AvailabilityCache $availability,
    ) {}

    /**
     * @param  array<string, mixed>  $capacities
     */
    public function __invoke(array $capacities): void
    {
        $current = $this->settings->current();

        $resolved = [];

        foreach (ReservationArea::cases() as $area) {
            $value = $capacities[$area->value] ?? null;

            if (is_numeric($value) && (int) $value >= 0) {
                $resolved[$area->value] = (int) $value;
            } else {
                $resolved[$area->value] = $current->capacityFor($area);
            }
        }

        $this->settings->save($current->withCapacities($resolved));

        $this->availability->forget();
    }
}
