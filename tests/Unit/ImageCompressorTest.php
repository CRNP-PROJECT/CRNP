<?php

declare(strict_types=1);

use App\Support\Image\ImageCompressor;
use App\Support\Image\ImagePreset;

/**
 * A deterministic, high-entropy JPEG so the encoder has to work — a flat colour
 * would compress to a few KB and never exercise the quality loop.
 */
function noisyJpeg(int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);
    mt_srand(21);

    for ($x = 0; $x < $width; $x += 2) {
        for ($y = 0; $y < $height; $y += 2) {
            $colour = imagecolorallocate($image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
            imagefilledrectangle($image, $x, $y, $x + 1, $y + 1, $colour);
        }
    }

    ob_start();
    imagejpeg($image, null, 95);
    $bytes = (string) ob_get_clean();
    imagedestroy($image);

    return $bytes;
}

it('resizes the longest edge down to ~800px and re-encodes under 300KB', function (): void {
    $compressed = (new ImageCompressor())->compress(noisyJpeg(1600, 1200));

    $info = getimagesizefromstring($compressed);

    expect($info)->not->toBeFalse()
        ->and(max($info[0], $info[1]))->toBe(800)
        ->and(strlen($compressed))->toBeLessThanOrEqual(300 * 1024);
});

it('keeps the aspect ratio of the resized image', function (): void {
    $compressed = (new ImageCompressor())->compress(noisyJpeg(1600, 1200));

    $info = getimagesizefromstring($compressed);

    expect($info)->not->toBeFalse()
        ->and($info[0])->toBe(800)
        ->and($info[1])->toBe(600);
});

it('never shrinks the longest edge below the 720px floor', function (): void {
    $compressed = (new ImageCompressor())->compress(noisyJpeg(750, 500));

    $info = getimagesizefromstring($compressed);

    expect($info)->not->toBeFalse()
        ->and(max($info[0], $info[1]))->toBeGreaterThanOrEqual(720);
});

it('leaves an already-small image at its source size without upscaling', function (): void {
    $compressed = (new ImageCompressor())->compress(noisyJpeg(600, 400));

    $info = getimagesizefromstring($compressed);

    expect($info)->not->toBeFalse()
        ->and($info[0])->toBe(600)
        ->and($info[1])->toBe(400);
});

it('emits a JPEG regardless of the source format', function (): void {
    $png = imagecreatetruecolor(900, 900);
    imagefilledrectangle($png, 0, 0, 900, 900, imagecolorallocate($png, 10, 120, 220));
    ob_start();
    imagepng($png);
    $source = (string) ob_get_clean();
    imagedestroy($png);

    $compressed = (new ImageCompressor())->compress($source);

    $info = getimagesizefromstring($compressed);

    expect($info)->not->toBeFalse()
        ->and($info[2])->toBe(IMAGETYPE_JPEG);
});

it('bounds a proof screenshot under 300KB without dropping below the 720px floor', function (): void {
    $compressed = (new ImageCompressor())->compress(noisyJpeg(2000, 1500), ImagePreset::Proof);

    $info = getimagesizefromstring($compressed);

    expect($info)->not->toBeFalse()
        ->and(strlen($compressed))->toBeLessThanOrEqual(300 * 1024)
        ->and(max($info[0], $info[1]))->toBeGreaterThanOrEqual(720);
});

it('never scales a proof screenshot past the proof preset edge ceiling', function (): void {
    $compressed = (new ImageCompressor())->compress(noisyJpeg(4000, 3000), ImagePreset::Proof);

    $info = getimagesizefromstring($compressed);

    expect($info)->not->toBeFalse()
        ->and(max($info[0], $info[1]))->toBeLessThanOrEqual(1600);
});

it('keeps the catalog preset at the 800px edge by default', function (): void {
    $compressed = (new ImageCompressor())->compress(noisyJpeg(1600, 1200));

    $info = getimagesizefromstring($compressed);

    expect($info)->not->toBeFalse()
        ->and(max($info[0], $info[1]))->toBe(800);
});
