<?php

declare(strict_types=1);

namespace App\Settings;

use Random\RandomException;

/**
 * Mints the kitchen display's shared secret.
 *
 * The secret lives in settings; the `/kitchen` route (#28) is a shared-secret
 * URL. "Rotate secret" replaces this value, so the old URL dies on the press
 * rather than at a redeploy — leaking it to a group chat is a same-day fix.
 */
final class KitchenSecret
{
    /**
     * A URL-safe, RTDB-safe token with 256 bits of entropy.
     */
    public static function generate(): string
    {
        try {
            return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        } catch (RandomException $exception) {
            // There is no safe fallback for a security token; surface the
            // failure rather than minting a weak secret.
            throw new \RuntimeException('Unable to generate a kitchen secret.', 0, $exception);
        }
    }
}
