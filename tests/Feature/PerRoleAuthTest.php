<?php

declare(strict_types=1);

use App\Staff\StaffRole;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

it('signs each area into its own guard from one shared session', function (): void {
    seedStaff('owner@example.com', StaffRole::Admin, 'Owner Santos');
    seedStaff('maria@example.com', StaffRole::Cashier, 'Maria Santos');

    Socialite::fake('google', SocialiteUser::fake(['id' => 'sub-owner', 'email' => 'owner@example.com']));
    $this->withSession(['auth.area' => 'admin'])
        ->get(route('auth.google.callback'))
        ->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticated('admin');
    $this->assertGuest('web');
    $this->assertGuest('cashier');

    Socialite::fake('google', SocialiteUser::fake(['id' => 'sub-maria', 'email' => 'maria@example.com']));
    $this->withSession(['auth.area' => 'cashier'])
        ->get(route('auth.google.callback'))
        ->assertRedirect(route('cashier.register'));

    // The cashier sign-in did not evict the admin one.
    $this->assertAuthenticated('cashier');
    $this->assertAuthenticated('admin');
    $this->assertGuest('web');

    $this->get('/admin')->assertOk();
    $this->get('/cashier')->assertOk();
});

it('signs a customer into only the customer area', function (): void {
    Socialite::fake('google', SocialiteUser::fake(['id' => 'sub-ada', 'email' => 'ada@example.com']));

    $this->get(route('auth.google.callback'))->assertRedirect(route('home'));

    $this->assertAuthenticated('web');
    $this->assertGuest('admin');
    $this->assertGuest('cashier');
});

it('signs out one area without disturbing another', function (): void {
    seedStaff('owner@example.com', StaffRole::Admin, 'Owner Santos');

    signIn('owner@example.com', 'Owner Santos');
    $this->get('/admin')->assertOk();

    $this->post(route('cashier.logout'))->assertRedirect(route('cashier.login'));

    $this->assertGuest('cashier');
    $this->assertAuthenticated('admin');
    $this->get('/admin')->assertOk();
});

it('remembers the area a staff sign-in began in for the shared callback', function (): void {
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.200'])
        ->get(route('auth.google.redirect', ['area' => 'admin']))
        ->assertRedirect()
        ->assertSessionHas('auth.area', 'admin');
});
