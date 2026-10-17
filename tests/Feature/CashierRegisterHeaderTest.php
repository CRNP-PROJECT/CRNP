<?php

declare(strict_types=1);

use App\Staff\StaffRole;

beforeEach(function (): void {
    seedStaff('maria@example.com', StaffRole::Cashier, 'Maria Santos');

    signIn('maria@example.com', 'Maria Santos');
});

it('names the cashier on duty in the register header', function (): void {
    $this->get('/cashier')
        ->assertOk()
        ->assertSee('Cashier on duty')
        ->assertSee('Maria Santos');
});

it('offers Switch account as this area\'s sign-out then sign-in', function (): void {
    $this->get('/cashier')->assertOk()->assertSee('Switch account');

    $this->post(route('cashier.logout'))->assertRedirect(route('cashier.login'));

    $this->assertGuest('cashier');

    $this->get(route('cashier.login'))->assertOk()->assertSee('Sign in with Google');
});
