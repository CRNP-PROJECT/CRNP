<?php

declare(strict_types=1);

use App\Staff\Actions\DeleteStaff;
use App\Staff\Actions\SetStaffRole;
use App\Staff\StaffRole;

it('refuses a Google account absent from the staff list at /cashier', function (): void {
    signIn('stranger@example.com');

    $this->get('/cashier')->assertForbidden();
});

it('refuses a Google account absent from the staff list at /admin', function (): void {
    signIn('stranger@example.com');

    $this->get('/admin')->assertForbidden();
});

it('lets a cashier into /cashier but not /admin', function (): void {
    seedStaff('cashier@example.com', StaffRole::Cashier, 'Maria Santos');

    signIn('cashier@example.com');

    $this->get('/cashier')->assertOk();
    $this->get('/admin')->assertForbidden();
});

it('lets an admin into /admin but not /cashier', function (): void {
    seedStaff('owner@example.com', StaffRole::Admin, 'Owner Santos');

    signIn('owner@example.com');

    $this->get('/admin')->assertOk();
    $this->get('/cashier')->assertForbidden();
});

it('sends a signed-out visitor to that area\'s own Google sign-in prompt', function (): void {
    $this->get('/cashier')->assertRedirect(route('cashier.login'));
    $this->get('/admin')->assertRedirect(route('admin.login'));
});

it('denies the next request after a demotion evicts the cached role', function (): void {
    $id = seedStaff('owner@example.com', StaffRole::Admin, 'Owner Santos');

    signIn('owner@example.com');

    $this->get('/admin')->assertOk(); // the guard caches role=admin here

    app(SetStaffRole::class)($id, StaffRole::Cashier);

    $this->get('/admin')->assertForbidden();
    $this->get('/cashier')->assertOk();
});

it('denies the next request after a deletion evicts the cached role', function (): void {
    $id = seedStaff('cashier@example.com', StaffRole::Cashier, 'Maria Santos');

    signIn('cashier@example.com');

    $this->get('/cashier')->assertOk(); // the guard caches role=cashier here

    app(DeleteStaff::class)($id);

    $this->get('/cashier')->assertForbidden();
});
