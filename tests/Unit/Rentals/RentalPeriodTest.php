<?php

declare(strict_types=1);

use App\Rentals\RentalPeriod;

/**
 * #34 — the rental overlap math, reused from the reservation conflict rule.
 *
 * A rental booking occupies an inclusive date range; two ranges overlap when
 * either start falls on or before the other's end. The availability math keys
 * on this, so an overlapping range is blocked.
 */

it('counts the days in an inclusive range', function (): void {
    expect(RentalPeriod::fromStrings('2026-10-08', '2026-10-10')->days())->toBe(3)
        ->and(RentalPeriod::fromStrings('2026-10-08', '2026-10-08')->days())->toBe(1);
});

it('treats a single-day range as covering exactly that date', function (): void {
    $period = RentalPeriod::fromStrings('2026-10-08', '2026-10-08');

    expect($period->covers(\Illuminate\Support\Carbon::parse('2026-10-08 23:00:00')))->toBeTrue()
        ->and($period->covers(\Illuminate\Support\Carbon::parse('2026-10-09 00:00:00')))->toBeFalse();
});

it('detects an overlap when ranges share a boundary day', function (): void {
    $a = RentalPeriod::fromStrings('2026-10-08', '2026-10-10');
    $b = RentalPeriod::fromStrings('2026-10-10', '2026-10-12');

    expect($a->overlaps($b))->toBeTrue()
        ->and($b->overlaps($a))->toBeTrue();
});

it('detects an overlap when one range contains the other', function (): void {
    $outer = RentalPeriod::fromStrings('2026-10-08', '2026-10-15');
    $inner = RentalPeriod::fromStrings('2026-10-10', '2026-10-11');

    expect($outer->overlaps($inner))->toBeTrue()
        ->and($inner->overlaps($outer))->toBeTrue();
});

it('treats adjacent ranges as non-overlapping', function (): void {
    $a = RentalPeriod::fromStrings('2026-10-08', '2026-10-09');
    $b = RentalPeriod::fromStrings('2026-10-10', '2026-10-11');

    expect($a->overlaps($b))->toBeFalse()
        ->and($b->overlaps($a))->toBeFalse();
});

it('rejects a range whose end is before its start', function (): void {
    expect(fn(): RentalPeriod => RentalPeriod::fromStrings('2026-10-10', '2026-10-08'))
        ->toThrow(InvalidArgumentException::class);
});
