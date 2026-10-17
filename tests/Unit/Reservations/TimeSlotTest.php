<?php

declare(strict_types=1);

use App\Reservations\Reservation;
use App\Reservations\ReservationStatus;
use App\Reservations\TimeSlot;
use App\Settings\ReservationArea;

/**
 * #33 — the derived slot rule behind the conflict check and the occupancy view.
 *
 * A reservation's wall-clock time collapses into a fixed two-hour slot, so two
 * bookings that share a slot share capacity. The rule is computed, never
 * stored, so it is tested here at the pure seam rather than through a screen.
 */
it('groups wall-clock times into two-hour slots anchored at even hours', function (): void {
    expect(TimeSlot::fromClock('18:00')->key())->toBe('18:00')
        ->and(TimeSlot::fromClock('18:15')->key())->toBe('18:00')
        ->and(TimeSlot::fromClock('19:30')->key())->toBe('18:00')
        ->and(TimeSlot::fromClock('20:00')->key())->toBe('20:00')
        ->and(TimeSlot::fromClock('21:45')->key())->toBe('20:00');
});

it('overlaps only when the date and slot both match', function (): void {
    $base = reservationAt('2026-10-08', '18:00');

    expect($base->overlaps(reservationAt('2026-10-08', '19:00')))->toBeTrue()
        ->and($base->overlaps(reservationAt('2026-10-08', '20:00')))->toBeFalse()
        ->and($base->overlaps(reservationAt('2026-10-09', '18:00')))->toBeFalse();
});

it('treats the same phone in the same slot as a duplicate', function (): void {
    $base = reservationAt('2026-10-08', '18:00');

    $samePhone = new Reservation(
        id: 'other',
        type: ReservationArea::DineIn,
        date: '2026-10-08',
        time: '18:30',
        party: 2,
        name: 'Ana Cruz',
        phone: '09171234567',
        status: ReservationStatus::Pending,
        preferredType: ReservationArea::DineIn,
    );

    expect($base->isDuplicateOf($samePhone))->toBeTrue();
});

function reservationAt(string $date, string $time): Reservation
{
    return new Reservation(
        id: 'r',
        type: ReservationArea::DineIn,
        date: $date,
        time: $time,
        party: 2,
        name: 'Ana Cruz',
        phone: '09171234567',
        status: ReservationStatus::Confirmed,
        preferredType: ReservationArea::DineIn,
    );
}
