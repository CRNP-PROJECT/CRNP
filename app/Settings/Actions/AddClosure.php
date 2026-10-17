<?php

declare(strict_types=1);

namespace App\Settings\Actions;

use App\Settings\Closure;
use App\Settings\SettingsRepository;

/**
 * Adds a scheduled date-range closure under a stable, RTDB-safe id.
 */
final readonly class AddClosure
{
    public function __construct(private SettingsRepository $settings) {}

    public function __invoke(Closure $closure): void
    {
        $this->settings->save(
            $this->settings->current()->withClosure(self::idFor($closure), $closure),
        );
    }

    /**
     * A deterministic id derived from the range, so re-adding the same holiday
     * overwrites rather than duplicates it. Base64url keeps the key clear of
     * RTDB's forbidden characters.
     */
    private static function idFor(Closure $closure): string
    {
        return rtrim(strtr(base64_encode("{$closure->from}:{$closure->to}"), '+/', '-_'), '=');
    }
}
