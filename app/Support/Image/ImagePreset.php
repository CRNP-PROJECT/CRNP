<?php

declare(strict_types=1);

namespace App\Support\Image;

/**
 * A re-encode target for {@see ImageCompressor}: the longest-edge ceiling, the
 * legibility floor, and the byte budget.
 *
 * `Catalog` matches the product/QR target (longest edge ~800px, the settings
 * copy feeds the admin screen's number). `Proof` keeps a GCash screenshot
 * legible — a taller 1600px ceiling so receipt text survives — while holding
 * the same 300 KB budget and 720px floor the client-side canvas compressor
 * uses. The server re-encodes to this preset and never trusts the client's
 * bound (PLAN §4 *Proof lifecycle*).
 */
enum ImagePreset
{
    case Catalog;

    case Proof;

    /**
     * The longest edge the image is scaled down to (never up).
     */
    public function maxEdge(): int
    {
        return match ($this) {
            self::Catalog => 800,
            self::Proof => 1600,
        };
    }

    /**
     * The legibility floor: the longest edge is never taken below this, so
     * receipt text stays readable.
     */
    public function floorEdge(): int
    {
        return 720;
    }

    /**
     * The byte ceiling the encoded JPEG must fit under.
     */
    public function maxBytes(): int
    {
        return 300 * 1024;
    }
}
