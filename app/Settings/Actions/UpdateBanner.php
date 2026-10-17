<?php

declare(strict_types=1);

namespace App\Settings\Actions;

use App\Settings\SettingsRepository;

/**
 * Persists the announcement banner text and its show/hide toggle.
 */
final readonly class UpdateBanner
{
    public function __construct(private SettingsRepository $settings) {}

    public function __invoke(string $text, bool $visible): void
    {
        $this->settings->save(
            $this->settings->current()->withBanner(trim($text), $visible),
        );
    }
}
