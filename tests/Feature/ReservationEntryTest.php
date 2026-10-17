<?php

declare(strict_types=1);

use App\Cache\AvailabilityCache;
use App\Reservations\ReservationStatus;
use App\Settings\ReservationArea;
use App\Staff\StaffRole;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * #33 — staff reservation entry.
 *
 * Entry is lookup-first: the phone typeahead prefills name, party and preferred
 * type for a returning customer, and a brand-new customer is captured in two
 * guided steps (≤3 then ≤2 inputs). The server runs the date-time conflict and
 * duplicate check before confirm and blocks with a reason.
 */
beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-10-06 10:00:00', 'Asia/Manila'));

    seedStaff('maria@example.com', StaffRole::Cashier, 'Maria Santos');

    signIn('maria@example.com', 'Maria Santos');

    seedSettings([
        'capacities' => [
            ReservationArea::DineIn->value => 4,
            ReservationArea::FunctionRoom->value => 10,
            ReservationArea::Catering->value => 20,
        ],
    ]);
});

it('prefills a returning customer from a phone typeahead', function (): void {
    seedReservation('r-past', [
        'name' => 'Ana Cruz',
        'phone' => '09171234567',
        'party' => 4,
        'preferredType' => ReservationArea::FunctionRoom->value,
    ]);

    $this->get('/cashier/reservations/lookup?phone=09171234567')
        ->assertOk()
        ->assertJsonPath('customers.0.name', 'Ana Cruz')
        ->assertJsonPath('customers.0.party', 4)
        ->assertJsonPath('customers.0.preferredType', ReservationArea::FunctionRoom->value);
});

it('prefills name, party and preferred type on the second step for a returning customer', function (): void {
    seedReservation('r-past', [
        'name' => 'Ana Cruz',
        'phone' => '09171234567',
        'party' => 4,
        'preferredType' => ReservationArea::FunctionRoom->value,
    ]);

    // The phone is the only value the cashier supplies; the server fills the rest.
    $this->post('/cashier/reservations/details', ['phone' => '09171234567'])
        ->assertOk()
        ->assertSee('Ana Cruz')
        ->assertSee('Function room')
        ->assertSee('value="4"', false);
});

it('captures a new customer in two steps of at most three then two inputs', function (): void {
    $stepOne = $this->get('/cashier/reservations/new')->assertOk();

    $fields = visibleFieldNames($stepOne->getContent());

    expect($fields)->toHaveCount(3)
        ->and($fields)->toContain('phone')
        ->and($fields)->toContain('name')
        ->and($fields)->toContain('type');

    $stepTwo = $this->post('/cashier/reservations/details', [
        'phone' => '09170000001',
        'name' => 'New Guest',
        'type' => ReservationArea::DineIn->value,
    ])->assertOk();

    $fieldsTwo = visibleFieldNames($stepTwo->getContent());

    expect($fieldsTwo)->toHaveCount(2)
        ->and($fieldsTwo)->toContain('when')
        ->and($fieldsTwo)->toContain('party');

    $this->post('/cashier/reservations', [
        'name' => 'New Guest',
        'phone' => '09170000001',
        'type' => ReservationArea::DineIn->value,
        'preferredType' => ReservationArea::DineIn->value,
        'when' => '2026-10-08T18:00',
        'party' => '4',
    ])->assertRedirect(route('cashier.reservations.index'));

    $rows = rtdb()->get('reservations');

    expect($rows)->toBeArray()->toHaveCount(1);

    $row = array_values($rows)[0];

    expect($row)->toMatchArray([
        'type' => ReservationArea::DineIn->value,
        'date' => '2026-10-08',
        'time' => '18:00',
        'party' => 4,
        'name' => 'New Guest',
        'phone' => '09170000001',
        'status' => ReservationStatus::Pending->value,
        'preferredType' => ReservationArea::DineIn->value,
    ]);
});

it('blocks a duplicate reservation with a reason', function (): void {
    seedReservation('r-existing', [
        'phone' => '09171234567',
        'date' => '2026-10-08',
        'time' => '18:00',
        'party' => 2,
        'status' => ReservationStatus::Confirmed->value,
    ]);

    $this->post('/cashier/reservations', [
        'name' => 'Ana Cruz',
        'phone' => '09171234567',
        'type' => ReservationArea::DineIn->value,
        'preferredType' => ReservationArea::DineIn->value,
        'when' => '2026-10-08T18:30',
        'party' => '2',
    ])->assertSessionHasErrors('reservation');

    expect(rtdb()->get('reservations'))->toBeArray()->toHaveCount(1);
});

it('blocks a reservation that would exceed the area capacity, with a reason', function (): void {
    seedReservation('r-existing', [
        'phone' => '09171111111',
        'date' => '2026-10-08',
        'time' => '18:00',
        'party' => 4,
        'type' => ReservationArea::DineIn->value,
        'status' => ReservationStatus::Confirmed->value,
    ]);

    $this->post('/cashier/reservations', [
        'name' => 'Walk In',
        'phone' => '09172222222',
        'type' => ReservationArea::DineIn->value,
        'preferredType' => ReservationArea::DineIn->value,
        'when' => '2026-10-08T18:15',
        'party' => '2',
    ])->assertSessionHasErrors('reservation');

    expect(rtdb()->get('reservations'))->toBeArray()->toHaveCount(1);
});

it('re-checks the conflict server-side when a pending reservation is confirmed', function (): void {
    $this->post('/cashier/reservations', [
        'name' => 'Ana Cruz',
        'phone' => '09171234567',
        'type' => ReservationArea::DineIn->value,
        'preferredType' => ReservationArea::DineIn->value,
        'when' => '2026-10-08T18:00',
        'party' => '2',
    ])->assertRedirect(route('cashier.reservations.index'));

    $rows = rtdb()->get('reservations');
    $id = (string) array_key_first($rows);

    // The slot fills up between entry and confirm: the confirm must re-check.
    seedReservation('r-late', [
        'phone' => '09173333333',
        'date' => '2026-10-08',
        'time' => '18:00',
        'party' => 4,
        'type' => ReservationArea::DineIn->value,
        'status' => ReservationStatus::Confirmed->value,
    ]);

    $this->post("/cashier/reservations/{$id}/confirm")
        ->assertSessionHasErrors('reservation');

    expect(rtdb()->get("reservations/{$id}/status"))->toBe(ReservationStatus::Pending->value);
});

it('shows confirm and cancel statuses on the reservation list', function (): void {
    seedReservation('r-pending', ['name' => 'Pending Guest', 'phone' => '09170000001', 'status' => ReservationStatus::Pending->value, 'date' => '2026-10-08', 'time' => '18:00']);
    seedReservation('r-confirmed', ['name' => 'Confirmed Guest', 'phone' => '09170000002', 'status' => ReservationStatus::Confirmed->value, 'date' => '2026-10-08', 'time' => '19:00']);
    seedReservation('r-cancelled', ['name' => 'Cancelled Guest', 'phone' => '09170000003', 'status' => ReservationStatus::Cancelled->value, 'date' => '2026-10-08', 'time' => '20:00']);

    $this->get('/cashier/reservations')
        ->assertOk()
        ->assertSee('Pending Guest')
        ->assertSee('Confirmed Guest')
        ->assertSee('Cancelled Guest')
        ->assertSee('Pending')
        ->assertSee('Confirmed')
        ->assertSee('Cancelled')
        ->assertSee('Confirm')
        ->assertSee('Cancel');

    $this->post('/cashier/reservations/r-pending/confirm')->assertRedirect();

    expect(rtdb()->get('reservations/r-pending/status'))->toBe(ReservationStatus::Confirmed->value);

    $this->post('/cashier/reservations/r-confirmed/cancel')->assertRedirect();

    expect(rtdb()->get('reservations/r-confirmed/status'))->toBe(ReservationStatus::Cancelled->value);
});

it('refuses reservations to a signed-in non-staff visitor', function (): void {
    signIn('visitor@example.com', 'Visitor');

    $this->get('/cashier/reservations')->assertForbidden();
    $this->get('/cashier/reservations/lookup')->assertForbidden();
});

it('lists the reservation types as tap-only chips on the first step', function (): void {
    $this->get('/cashier/reservations/new')
        ->assertOk()
        ->assertSee(ReservationArea::DineIn->label())
        ->assertSee(ReservationArea::FunctionRoom->label())
        ->assertSee(ReservationArea::Catering->label());
});

it('evicts the availability cache when a reservation is booked', function (): void {
    $this->get('/occupancy')->assertOk();

    expect(Cache::has(AvailabilityCache::KEY))->toBeTrue();

    $this->post('/cashier/reservations', [
        'name' => 'Ana Cruz',
        'phone' => '09171234567',
        'type' => ReservationArea::DineIn->value,
        'preferredType' => ReservationArea::DineIn->value,
        'when' => '2026-10-08T18:00',
        'party' => '2',
    ])->assertRedirect(route('cashier.reservations.index'));

    expect(Cache::has(AvailabilityCache::KEY))->toBeFalse();
});
