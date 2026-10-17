<?php

declare(strict_types=1);

namespace App\Settings\Actions;

use App\Settings\KitchenSecret;
use App\Settings\SettingsRepository;

/**
 * Replaces the kitchen display's shared secret.
 *
 * The old URL stops matching the instant this write lands, so the kiosk reloads
 * with the new link — no redeploy required.
 */
final readonly class RotateKitchenSecret
{
    public function __construct(private SettingsRepository $settings) {}

    public function __invoke(): string
    {
        $secret = KitchenSecret::generate();

        $this->settings->save($this->settings->current()->withKitchenSecret($secret));

        return $secret;
    }
}
