<?php

declare(strict_types=1);

use App\Staff\StaffDirectory;

beforeEach(function (): void {
    config([
        'staff.admin.name' => 'Owner Santos',
        'staff.admin.email' => 'owner@example.com',
    ]);
});

it('seeds the first admin from the environment', function (): void {
    $this->artisan('staff:seed', ['--admin' => true])->assertSuccessful();

    $id = StaffDirectory::idForEmail('owner@example.com');

    expect(rtdb()->get("staff/{$id}"))->toMatchArray([
        'name' => 'Owner Santos',
        'email' => 'owner@example.com',
        'role' => 'admin',
    ]);
});

it('refuses a second run once a staff row exists', function (): void {
    $this->artisan('staff:seed', ['--admin' => true])->assertSuccessful();
    $this->artisan('staff:seed', ['--admin' => true])->assertFailed();

    expect(array_keys(rtdb()->get('staff')))->toHaveCount(1);
});

it('refuses when the admin environment variables are missing', function (): void {
    config(['staff.admin.name' => null, 'staff.admin.email' => null]);

    $this->artisan('staff:seed', ['--admin' => true])->assertFailed();

    expect(rtdb()->get('staff'))->toBeNull();
});

it('refuses to run without the --admin flag', function (): void {
    $this->artisan('staff:seed')->assertFailed();

    expect(rtdb()->get('staff'))->toBeNull();
});

it('lets the seeded admin reach /admin', function (): void {
    $this->artisan('staff:seed', ['--admin' => true])->assertSuccessful();

    signIn('owner@example.com');

    $this->get('/admin')->assertOk();
});

it('keys the seeded admin by a stable email-derived id', function (): void {
    $this->artisan('staff:seed', ['--admin' => true])->assertSuccessful();

    $id = StaffDirectory::idForEmail('OWNER@example.com');

    expect(rtdb()->get("staff/{$id}"))->toMatchArray(['role' => 'admin']);
});
