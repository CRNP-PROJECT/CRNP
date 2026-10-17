<?php

declare(strict_types=1);

use App\Cache\AvailabilityCache;
use App\Reservations\ReservationStatus;
use App\Settings\ReservationArea;
use App\Staff\StaffRole;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * #33 — the public occupancy view.
 *
 * Read-only on the public site: seats taken vs capacity per day and time slot
 * for the week ahead, confirmed bookings only, server-aggregated, aggregate
 * only (no names, no phones) and it books nothing. Served from the availability
 * cache, evicted by booking and capacity writes.
 */
beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-10-06 10:00:00', 'Asia/Manila'));

    seedSettings([
        'capacities' => [
            ReservationArea::DineIn->value => 40,
            ReservationArea::FunctionRoom->value => 10,
            ReservationArea::Catering->value => 20,
        ],
    ]);
});

it('shows seats taken vs capacity per day and slot for confirmed bookings, aggregate only', function (): void {
    seedReservation('r1', [
        'date' => '2026-10-08',
        'time' => '18:00',
        'party' => 2,
        'type' => ReservationArea::DineIn->value,
        'status' => ReservationStatus::Confirmed->value,
        'name' => 'Ana Cruz',
        'phone' => '09171234567',
    ]);
    seedReservation('r2', [
        'date' => '2026-10-08',
        'time' => '19:00',
        'party' => 3,
        'type' => ReservationArea::DineIn->value,
        'status' => ReservationStatus::Confirmed->value,
        'name' => 'Ben Reyes',
        'phone' => '09170000002',
    ]);

    $this->get('/occupancy')
        ->assertOk()
        ->assertSee('2026-10-08')
        ->assertSee('Dine-in')
        ->assertSee('5 / 40')
        ->assertDontSee('Ana Cruz')
        ->assertDontSee('Ben Reyes')
        ->assertDontSee('09171234567');
});

it('counts confirmed bookings only, never pending or cancelled', function (): void {
    seedReservation('r-cancelled', [
        'date' => '2026-10-08',
        'party' => 4,
        'status' => ReservationStatus::Cancelled->value,
    ]);
    seedReservation('r-pending', [
        'date' => '2026-10-08',
        'party' => 3,
        'status' => ReservationStatus::Pending->value,
    ]);

    $this->get('/occupancy')
        ->assertOk()
        ->assertDontSee('7 / 40');
});

it('covers the week ahead and nothing beyond it', function (): void {
    seedReservation('r-in', [
        'date' => '2026-10-08',
        'party' => 2,
        'status' => ReservationStatus::Confirmed->value,
    ]);
    seedReservation('r-out', [
        'date' => '2026-10-20',
        'party' => 2,
        'status' => ReservationStatus::Confirmed->value,
    ]);

    $this->get('/occupancy')
        ->assertOk()
        ->assertSee('2026-10-06')
        ->assertSee('2026-10-08')
        ->assertSee('2026-10-12')
        ->assertDontSee('2026-10-13')
        ->assertDontSee('2026-10-20');
});

it('books nothing from the public occupancy view', function (): void {
    $this->get('/occupancy')
        ->assertOk()
        ->assertDontSee('<form', false);
});

it('serves the occupancy view from the availability cache', function (): void {
    $this->get('/occupancy')->assertOk();

    expect(Cache::has(AvailabilityCache::KEY))->toBeTrue();
});

it('evicts the availability cache on a capacity write', function (): void {
    seedStaff('owner@example.com', StaffRole::Admin, 'Owner Santos');

    $this->get('/occupancy')->assertOk();

    expect(Cache::has(AvailabilityCache::KEY))->toBeTrue();

    signIn('owner@example.com', 'Owner Santos');

    $this->put('/admin/settings/capacities', [
        'capacities' => [ReservationArea::DineIn->value => '50'],
    ])->assertRedirect(route('admin.settings.edit'));

    expect(Cache::has(AvailabilityCache::KEY))->toBeFalse();
});
