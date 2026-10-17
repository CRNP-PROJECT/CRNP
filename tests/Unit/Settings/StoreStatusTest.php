<?php

declare(strict_types=1);

use App\Settings\ClosedReason;
use App\Settings\StoreStatus;
use Illuminate\Support\Carbon;

it('is open during the configured hours for the weekday', function (): void {
    seedSettings([
        'hours' => [
            'mon' => ['open' => '09:00', 'close' => '21:00'],
            'tue' => ['open' => '09:00', 'close' => '21:00'],
        ],
    ]);

    $status = app(StoreStatus::class);

    expect($status->isOpenAt(Carbon::parse('2026-10-06 12:00:00', 'Asia/Manila')))->toBeTrue();
});

it('is closed before opening and at or after closing', function (): void {
    seedSettings([
        'hours' => [
            'tue' => ['open' => '09:00', 'close' => '21:00'],
        ],
    ]);

    $status = app(StoreStatus::class);

    expect($status->isOpenAt(Carbon::parse('2026-10-06 08:59:00', 'Asia/Manila')))->toBeFalse()
        ->and($status->isOpenAt(Carbon::parse('2026-10-06 20:59:00', 'Asia/Manila')))->toBeTrue()
        ->and($status->isOpenAt(Carbon::parse('2026-10-06 21:00:00', 'Asia/Manila')))->toBeFalse();
});

it('is closed on a weekday with no configured hours', function (): void {
    seedSettings([
        'hours' => [
            'mon' => ['open' => '09:00', 'close' => '21:00'],
        ],
    ]);

    expect(app(StoreStatus::class)->isOpenAt(Carbon::parse('2026-10-06 12:00:00', 'Asia/Manila')))
        ->toBeFalse();
});

it('is closed whenever the force-close toggle is on', function (): void {
    seedSettings([
        'hours' => ['tue' => ['open' => '09:00', 'close' => '21:00']],
        'forceClose' => true,
    ]);

    $state = app(StoreStatus::class)->stateAt(Carbon::parse('2026-10-06 12:00:00', 'Asia/Manila'));

    expect($state->isOpen)->toBeFalse()
        ->and($state->reason)->toBe(ClosedReason::ForceClose);
});

it('is closed on a date inside a scheduled closure and open outside it', function (): void {
    seedSettings([
        'hours' => [
            'wed' => ['open' => '09:00', 'close' => '21:00'],
            'thu' => ['open' => '09:00', 'close' => '21:00'],
            'fri' => ['open' => '09:00', 'close' => '21:00'],
        ],
        'closures' => [
            'christmas' => ['from' => '2026-12-24', 'to' => '2026-12-26', 'label' => 'Christmas'],
        ],
    ]);

    $status = app(StoreStatus::class);

    expect($status->isOpenAt(Carbon::parse('2026-12-25 12:00:00', 'Asia/Manila')))->toBeFalse()
        ->and($status->isOpenAt(Carbon::parse('2026-12-23 12:00:00', 'Asia/Manila')))->toBeTrue();
});

it('evaluates the wall clock in Asia/Manila regardless of the instant timezone', function (): void {
    seedSettings([
        'hours' => ['tue' => ['open' => '09:00', 'close' => '21:00']],
    ]);

    // 01:00 UTC on Tuesday is 09:00 in Manila — opening time.
    expect(app(StoreStatus::class)->isOpenAt(Carbon::parse('2026-10-06 01:00:00', 'UTC')))
        ->toBeTrue();
});
