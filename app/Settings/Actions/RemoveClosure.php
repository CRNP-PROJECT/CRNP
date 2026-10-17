<?php

declare(strict_types=1);

namespace App\Settings\Actions;

use App\Settings\SettingsRepository;

/**
 * Removes a scheduled closure so the site is no longer closed by it.
 */
final readonly class RemoveClosure
{
    public function __construct(private SettingsRepository $settings) {}

    public function __invoke(string $id): void
    {
        $this->settings->save($this->settings->current()->withoutClosure($id));
    }
}
