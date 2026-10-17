<?php

declare(strict_types=1);

use App\Orders\PickupTime;

/**
 * #23 — the pickup time defaults to ASAP and the earliest selectable time is
 * now + 15 minutes.
 *
 * The value object is the one place the 15-minute lead is defined, so the
 * picker's `min`, the server-side validation, and the persisted node all agree.
 * All wall-clock evaluation is in the business timezone.
 */

it('defaults to ASAP when no pickup time is supplied', function (): void {
    $now = new DateTimeImmutable('2026-10-06 12:00:00', new DateTimeZone('Asia/Manila'));

    $pickup = PickupTime::fromInput(null, $now);

    expect($pickup->isAsap())->toBeTrue()
        ->and($pickup->toNode())->toBeNull();
});

it('treats an empty or "asap" input as ASAP', function (): void {
    $now = new DateTimeImmutable('2026-10-06 12:00:00', new DateTimeZone('Asia/Manila'));

    expect(PickupTime::fromInput('', $now)->isAsap())->toBeTrue()
        ->and(PickupTime::fromInput('asap', $now)->isAsap())->toBeTrue();
});

it('sets the earliest selectable pickup to now + 15 minutes', function (): void {
    $now = new DateTimeImmutable('2026-10-06 12:00:00', new DateTimeZone('Asia/Manila'));

    $earliest = PickupTime::earliest($now)->setTimezone(new DateTimeZone('Asia/Manila'));

    expect($earliest->format('Y-m-d H:i'))->toBe('2026-10-06 12:15');
});

it('accepts a pickup time exactly at the earliest boundary', function (): void {
    $now = new DateTimeImmutable('2026-10-06 12:00:00', new DateTimeZone('Asia/Manila'));

    $pickup = PickupTime::fromInput('2026-10-06T12:15', $now);

    expect($pickup->isAsap())->toBeFalse()
        ->and($pickup->at?->setTimezone(new DateTimeZone('Asia/Manila'))->format('Y-m-d H:i'))->toBe('2026-10-06 12:15');
});

it('refuses a pickup time earlier than now + 15 minutes', function (): void {
    $now = new DateTimeImmutable('2026-10-06 12:00:00', new DateTimeZone('Asia/Manila'));

    expect(fn() => PickupTime::fromInput('2026-10-06T12:14', $now))
        ->toThrow(InvalidArgumentException::class);
});

it('stores a scheduled pickup as a UTC ISO instant', function (): void {
    $now = new DateTimeImmutable('2026-10-06 12:00:00', new DateTimeZone('Asia/Manila'));

    $pickup = PickupTime::fromInput('2026-10-06T13:00', $now);

    expect($pickup->toNode())->toBe('2026-10-06T05:00:00Z');
});
