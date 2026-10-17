<?php

declare(strict_types=1);

namespace App\Settings\Actions;

use App\Settings\Hours;
use App\Settings\SettingsRepository;
use App\Settings\Weekday;

/**
 * Persists hours per weekday and the force-close toggle in one write.
 *
 * The force-close stays for emergencies; scheduled closures are configured
 * separately. A blank weekday is closed. The write evicts the catalog cache so
 * an emergency close reaches the customer site on the next request.
 */
final readonly class UpdateBusinessHours
{
    public function __construct(private SettingsRepository $settings) {}

    /**
     * @param  array<string, mixed>  $hours  raw `hours.{day}.{open,close}` input
     */
    public function __invoke(array $hours, bool $forceClose): void
    {
        $windows = [];

        foreach (Weekday::cases() as $day) {
            $row = $hours[$day->value] ?? null;

            if (! is_array($row)) {
                continue;
            }

            $window = Hours::fromInput($row['open'] ?? null, $row['close'] ?? null);

            if ($window !== null) {
                $windows[$day->value] = $window;
            }
        }

        $this->settings->save(
            $this->settings->current()->withBusinessHours($windows, $forceClose),
        );
    }
}
