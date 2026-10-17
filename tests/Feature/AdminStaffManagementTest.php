<?php

declare(strict_types=1);

use App\Staff\StaffDirectory;
use App\Staff\StaffRole;

beforeEach(function (): void {
    seedStaff('owner@example.com', StaffRole::Admin, 'Owner Santos');

    signIn('owner@example.com');
});

it('lists staff accounts on the staff screen', function (): void {
    seedStaff('maria@example.com', StaffRole::Cashier, 'Maria Santos');

    $this->get('/admin/staff')
        ->assertOk()
        ->assertSee('Maria Santos')
        ->assertSee('maria@example.com')
        ->assertSee('Add a staff account');
});

it('lets an admin create a staff account by name and Google email', function (): void {
    $this->post('/admin/staff', [
        'name' => 'Maria Santos',
        'email' => 'maria@example.com',
        'role' => 'cashier',
    ])->assertRedirect(route('admin.staff.index'));

    $id = StaffDirectory::idForEmail('maria@example.com');

    expect(rtdb()->get("staff/{$id}"))->toMatchArray([
        'name' => 'Maria Santos',
        'email' => 'maria@example.com',
        'role' => 'cashier',
    ]);
});

it('lets the created cashier reach /cashier on their next sign in', function (): void {
    $this->post('/admin/staff', [
        'name' => 'Maria Santos',
        'email' => 'maria@example.com',
        'role' => 'cashier',
    ]);

    signIn('maria@example.com', 'Maria Santos', 'sub-maria');

    $this->get('/cashier')->assertOk()->assertSee('Maria Santos');
});

it('refuses a duplicate staff email and leaves the first account intact', function (): void {
    $this->post('/admin/staff', ['name' => 'First', 'email' => 'dup@example.com', 'role' => 'cashier']);

    $this->post('/admin/staff', ['name' => 'Second', 'email' => 'dup@example.com', 'role' => 'admin'])
        ->assertSessionHasErrors('email');

    $id = StaffDirectory::idForEmail('dup@example.com');

    expect(rtdb()->get("staff/{$id}"))->toMatchArray(['name' => 'First', 'role' => 'cashier']);
});

it('rejects a staff email that is not an email address', function (): void {
    $this->post('/admin/staff', ['name' => 'Maria', 'email' => 'not-an-email', 'role' => 'cashier'])
        ->assertSessionHasErrors('email');
});

it('demotes a staff account', function (): void {
    $id = seedStaff('cashier@example.com', StaffRole::Admin, 'Maria Santos');

    $this->put("/admin/staff/{$id}", ['role' => 'cashier'])
        ->assertRedirect(route('admin.staff.index'));

    expect(rtdb()->get("staff/{$id}"))->toMatchArray(['role' => 'cashier']);
});

it('deletes a staff account', function (): void {
    $id = seedStaff('cashier@example.com', StaffRole::Cashier, 'Maria Santos');

    $this->delete("/admin/staff/{$id}")->assertRedirect(route('admin.staff.index'));

    expect(rtdb()->get("staff/{$id}"))->toBeNull();
});

it('refuses staff management to a cashier', function (): void {
    seedStaff('cashier@example.com', StaffRole::Cashier, 'Maria Santos');

    signIn('cashier@example.com');

    $this->get('/admin/staff')->assertForbidden();
    $this->post('/admin/staff', ['name' => 'X', 'email' => 'x@example.com', 'role' => 'cashier'])
        ->assertForbidden();
});

it('refuses staff management to a signed-out visitor', function (): void {
    signOut();

    $this->get('/admin/staff')->assertRedirect(route('admin.login'));
    $this->post('/admin/staff', ['name' => 'X', 'email' => 'x@example.com', 'role' => 'cashier'])
        ->assertRedirect(route('admin.login'));
});

it('exposes no public staff signup route', function (): void {
    $staffRoutes = collect(Route::getRoutes()->getRoutes())
        ->map(fn($route): string => $route->uri())
        ->filter(fn(string $uri): bool => str_contains($uri, 'staff'))
        ->values()
        ->all();

    expect($staffRoutes)->not->toBeEmpty();

    foreach ($staffRoutes as $uri) {
        expect($uri)->toStartWith('admin/');
    }
});

it('offers sign-out on every admin screen', function (): void {
    // The admin nav has a single source of truth (admin.partials.nav); every
    // admin screen must include it so a signed-in admin can always get out.
    $screens = [
        'admin.dashboard',
        'admin.reports.sales',
        'admin.reports.payments',
        'admin.activity',
        'admin.products.index',
        'admin.categories.index',
        'admin.rentals.index',
        'admin.staff.index',
        'admin.settings.edit',
    ];

    foreach ($screens as $route) {
        $this->get(route($route))
            ->assertOk()
            ->assertSee('Sign out')
            ->assertSee(route('admin.logout'));
    }
});
