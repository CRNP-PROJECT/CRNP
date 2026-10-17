<?php

declare(strict_types=1);

use App\Staff\StaffRole;

it('refuses product management to a cashier', function (): void {
    seedStaff('owner@example.com', StaffRole::Admin, 'Owner Santos');
    seedStaff('cashier@example.com', StaffRole::Cashier, 'Maria Santos');
    seedCategory('drinks', 'Drinks');

    signIn('cashier@example.com');

    $this->get('/admin/products')->assertForbidden();
    $this->get('/admin/categories')->assertForbidden();
    $this->post('/admin/products', ['name' => 'X', 'price' => '1', 'categoryId' => 'drinks'])->assertForbidden();
    $this->post('/admin/categories', ['name' => 'X', 'order' => '1'])->assertForbidden();
});

it('refuses product management to a signed-out visitor', function (): void {
    seedStaff('owner@example.com', StaffRole::Admin, 'Owner Santos');

    $this->get('/admin/products')->assertRedirect(route('admin.login'));
    $this->get('/admin/categories')->assertRedirect(route('admin.login'));
    $this->post('/admin/categories', ['name' => 'X', 'order' => '1'])->assertRedirect(route('admin.login'));
});
