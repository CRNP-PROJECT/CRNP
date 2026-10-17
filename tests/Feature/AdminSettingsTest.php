<?php

declare(strict_types=1);

use App\Cache\CatalogCache;
use App\Settings\ReservationArea;
use App\Settings\SettingsRepository;
use App\Staff\StaffRole;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    seedStaff('owner@example.com', StaffRole::Admin, 'Owner Santos');

    signIn('owner@example.com');
});

it('shows the settings screen with the Complete setup banner while the payment block is missing', function (): void {
    $this->get('/admin/settings')
        ->assertOk()
        ->assertSee('Complete setup')
        ->assertSee('Business hours')
        ->assertSee('Announcement banner')
        ->assertSee('GCash payment')
        ->assertSee('Kitchen secret')
        ->assertSee('Reservation capacities');
});

it('lets an admin edit hours per weekday and the force-close toggle', function (): void {
    $this->put('/admin/settings/hours', [
        'hours' => [
            'mon' => ['open' => '09:00', 'close' => '21:00'],
            'tue' => ['open' => '10:00', 'close' => '20:00'],
        ],
        'forceClose' => '1',
    ])->assertRedirect(route('admin.settings.edit'));

    $node = rtdb()->get('settings');

    expect($node)->toBeArray()
        ->and($node['hours'])->toBe([
            'mon' => ['open' => '09:00', 'close' => '21:00'],
            'tue' => ['open' => '10:00', 'close' => '20:00'],
        ])
        ->and($node['forceClose'])->toBeTrue();
});

it('drops a weekday whose hours are left blank', function (): void {
    $this->put('/admin/settings/hours', [
        'hours' => [
            'mon' => ['open' => '09:00', 'close' => '21:00'],
            'tue' => ['open' => '', 'close' => ''],
        ],
    ])->assertRedirect(route('admin.settings.edit'));

    expect(rtdb()->get('settings/hours'))->toBe([
        'mon' => ['open' => '09:00', 'close' => '21:00'],
    ]);
});

it('rejects hours whose closing time is not after opening', function (): void {
    $this->put('/admin/settings/hours', [
        'hours' => [
            'mon' => ['open' => '21:00', 'close' => '09:00'],
        ],
    ])->assertSessionHasErrors('hours.mon.close');

    expect(rtdb()->get('settings/hours'))->toBeNull();
});

it('lets an admin save the announcement banner with a show/hide toggle', function (): void {
    $this->put('/admin/settings/banner', [
        'banner' => 'Buy 1 take 1 milktea this weekend!',
        'bannerVisible' => '1',
    ])->assertRedirect(route('admin.settings.edit'));

    expect(rtdb()->get('settings/banner'))->toBe([
        'text' => 'Buy 1 take 1 milktea this weekend!',
        'visible' => true,
    ]);
});

it('hides the banner when the toggle is off', function (): void {
    $this->put('/admin/settings/banner', [
        'banner' => 'Quiet week',
    ])->assertRedirect(route('admin.settings.edit'));

    expect(rtdb()->get('settings/banner'))->toBe(['text' => 'Quiet week', 'visible' => false]);
});

it('lets an admin add and remove a scheduled date-range closure', function (): void {
    $this->post('/admin/settings/closures', [
        'from' => '2026-12-24',
        'to' => '2026-12-26',
        'label' => 'Christmas',
    ])->assertRedirect(route('admin.settings.edit'));

    $closures = rtdb()->get('settings/closures');

    expect($closures)->toBeArray()->toHaveCount(1);

    $id = array_key_first($closures);

    expect($closures[$id])->toBe([
        'from' => '2026-12-24',
        'to' => '2026-12-26',
        'label' => 'Christmas',
    ]);

    $this->delete("/admin/settings/closures/{$id}")
        ->assertRedirect(route('admin.settings.edit'));

    expect(rtdb()->get('settings/closures'))->toBe([]);
});

it('rejects a closure whose end date is before its start', function (): void {
    $this->post('/admin/settings/closures', [
        'from' => '2026-12-26',
        'to' => '2026-12-24',
        'label' => 'Backwards',
    ])->assertSessionHasErrors('to');

    expect(rtdb()->get('settings/closures'))->toBeNull();
});

it('stores the resized QR as base64 in the settings node', function (): void {
    $this->post('/admin/settings/payment', [
        'gcashNumber' => '0917 123 4567',
        'qr' => UploadedFile::fake()->image('gcash-qr.png', 1600, 1600),
    ])->assertRedirect(route('admin.settings.edit'));

    $node = rtdb()->get('settings/gcash');

    expect($node)->toBeArray()
        ->and($node['number'])->toBe('0917 123 4567')
        ->and($node['qr'])->toBeString()->toStartWith('data:image/jpeg;base64,');

    $bytes = base64_decode(substr($node['qr'], strlen('data:image/jpeg;base64,')), true);

    expect($bytes)->toBeString();

    $info = getimagesizefromstring((string) $bytes);

    expect($info)->not->toBeFalse()
        ->and($info[2])->toBe(IMAGETYPE_JPEG)
        ->and(max($info[0], $info[1]))->toBe(800);
});

it('rejects a QR upload that is not an image', function (): void {
    $this->post('/admin/settings/payment', [
        'gcashNumber' => '0917 123 4567',
        'qr' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
    ])->assertSessionHasErrors('qr');

    expect(rtdb()->get('settings/gcash'))->toBeNull();
});

it('flips the Complete setup banner once both the number and QR are configured', function (): void {
    $this->put('/admin/settings/banner', ['banner' => 'Hello']);

    // Number only: the gate is still incomplete.
    $this->post('/admin/settings/payment', ['gcashNumber' => '0917 123 4567']);
    $this->get(route('admin.dashboard'))->assertSee('Complete setup');

    // Both configured: the banner clears.
    $this->post('/admin/settings/payment', [
        'gcashNumber' => '0917 123 4567',
        'qr' => UploadedFile::fake()->image('gcash-qr.png', 900, 900),
    ]);

    $this->get(route('admin.dashboard'))->assertDontSee('Complete setup');
});

it('keeps the stored QR when the payment form is submitted without a new file', function (): void {
    $this->post('/admin/settings/payment', [
        'gcashNumber' => '0917 123 4567',
        'qr' => UploadedFile::fake()->image('gcash-qr.png', 900, 900),
    ]);

    $qr = rtdb()->get('settings/gcash/qr');

    $this->post('/admin/settings/payment', ['gcashNumber' => '0918 000 0000'])
        ->assertRedirect(route('admin.settings.edit'));

    expect(rtdb()->get('settings/gcash/qr'))->toBe($qr)
        ->and(rtdb()->get('settings/gcash/number'))->toBe('0918 000 0000');
});

it('rotates the kitchen secret and invalidates the old one', function (): void {
    $this->post('/admin/settings/kitchen-secret')->assertRedirect(route('admin.settings.edit'));

    $first = rtdb()->get('settings/kitchenSecret');

    expect($first)->toBeString()->not->toBe('');

    $this->post('/admin/settings/kitchen-secret')->assertRedirect(route('admin.settings.edit'));

    $second = rtdb()->get('settings/kitchenSecret');

    expect($second)->toBeString()->not->toBe('')
        ->and($second)->not->toBe($first);

    // The stored value is the new secret, so the old link no longer matches.
    $settings = app(SettingsRepository::class)->current();

    expect($settings->kitchenSecret)->toBe($second)
        ->and($settings->kitchenSecret)->not->toBe($first);

    // The admin can read the live board link off the settings screen.
    $this->get(route('admin.settings.edit'))
        ->assertOk()
        ->assertSee(route('kitchen.board.secret', ['secret' => $second]), false);
});

it('lets an admin edit reservation capacities per area', function (): void {
    $this->put('/admin/settings/capacities', [
        'capacities' => [
            'dine_in' => '24',
            'function_room' => '80',
            'catering' => '150',
        ],
    ])->assertRedirect(route('admin.settings.edit'));

    expect(rtdb()->get('settings/capacities'))->toBe([
        'dine_in' => 24,
        'function_room' => 80,
        'catering' => 150,
    ]);
});

it('falls back to a smart default capacity for an area the admin left unset', function (): void {
    $this->put('/admin/settings/capacities', [
        'capacities' => ['dine_in' => '24'],
    ])->assertRedirect(route('admin.settings.edit'));

    $settings = app(SettingsRepository::class)->current();

    expect($settings->capacityFor(ReservationArea::DineIn))->toBe(24)
        ->and($settings->capacityFor(ReservationArea::Catering))->toBe(100);
});

it('evicts the catalog cache on a settings write', function (): void {
    rtdb()->set('products/p1', ['name' => 'Milktea']);

    // Prime the shared catalog entry the customer site reads through.
    $cached = rtdb()->cached(CatalogCache::KEY, 'products', 300);

    expect($cached)->toBeArray()->and(Cache::has(CatalogCache::KEY))->toBeTrue();

    $this->put('/admin/settings/hours', [
        'hours' => ['mon' => ['open' => '09:00', 'close' => '21:00']],
        'forceClose' => '1',
    ])->assertRedirect(route('admin.settings.edit'));

    expect(Cache::has(CatalogCache::KEY))->toBeFalse();
});

it('refuses settings to a cashier', function (): void {
    seedStaff('cashier@example.com', StaffRole::Cashier, 'Maria Santos');

    signIn('cashier@example.com');

    $this->get('/admin/settings')->assertForbidden();
    $this->post('/admin/settings/kitchen-secret')->assertForbidden();
});

it('refuses settings to a signed-out visitor', function (): void {
    signOut();

    $this->get('/admin/settings')->assertRedirect(route('admin.login'));
    $this->post('/admin/settings/kitchen-secret')->assertRedirect(route('admin.login'));
});
