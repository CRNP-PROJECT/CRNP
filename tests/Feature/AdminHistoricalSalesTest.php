<?php

declare(strict_types=1);

use App\Staff\StaffRole;

/**
 * The **historical sales encoding** surface: an admin records a past sale into
 * the `sales/{id}` ledger on a chosen date, and every existing reader (reports,
 * payments, dashboard) picks it up with no change.
 *
 * The row is shaped like a settled walk-in but keyed `hist-{ulid}` and carries
 * no `handledBy`, so it never collides with an order and never lands in the
 * per-cashier Activity totals. The chosen date becomes `soldAt` at noon
 * Asia/Manila (`04:00Z`), inside both the UTC report range and the business
 * day.
 */

beforeEach(function (): void {
    seedStaff('owner@example.com', StaffRole::Admin, 'Owner Santos');
    signIn('owner@example.com', 'Owner Santos');

    $this->travelTo(new DateTimeImmutable('2026-10-06 12:00:00', new DateTimeZone('Asia/Manila')));
});

it('encodes a past sale that appears on its chosen date in the sales report', function (): void {
    seedProduct('p1', ['name' => 'Milktea', 'price' => 120.0]);

    $this->post(route('admin.historical.store'), [
        'date' => '2026-10-01',
        'method' => 'cash',
        'lines' => [['productId' => 'p1', 'quantity' => '2', 'unitPrice' => '120']],
    ])->assertRedirect(route('admin.historical.create'));

    $sales = rtdb()->get('sales');

    expect($sales)->toBeArray()->toHaveCount(1);

    $sale = array_values($sales)[0];

    expect($sale['total'])->toBe(240.0)
        ->and($sale['soldAt'])->toBe('2026-10-01T04:00:00Z')
        ->and($sale['type'])->toBe('walkin')
        ->and(array_key_first($sales))->toStartWith('hist-');

    $this->get(route('admin.reports.sales', ['from' => '2026-10-01', 'to' => '2026-10-01']))
        ->assertOk()
        ->assertSee('₱240.00');
});

it('falls back to the product current price when the unit price is left blank', function (): void {
    seedProduct('p1', ['name' => 'Milktea', 'price' => 120.0]);

    $this->post(route('admin.historical.store'), [
        'date' => '2026-10-01',
        'method' => 'cash',
        'lines' => [['productId' => 'p1', 'quantity' => '3', 'unitPrice' => '']],
    ])->assertRedirect();

    expect(array_values(rtdb()->get('sales'))[0]['total'])->toBe(360.0);
});

it('records no handledBy so the sale stays out of the cashier activity totals', function (): void {
    seedProduct('p1');

    $this->post(route('admin.historical.store'), [
        'date' => '2026-10-01',
        'method' => 'cash',
        'lines' => [['productId' => 'p1', 'quantity' => '1']],
    ])->assertRedirect();

    expect(array_values(rtdb()->get('sales'))[0])->not->toHaveKey('handledBy');
});

it('counts an encoded GCash sale on the GCash side of the payments report', function (): void {
    seedProduct('p1', ['price' => 100.0]);

    $this->post(route('admin.historical.store'), [
        'date' => '2026-10-01',
        'method' => 'gcash',
        'lines' => [['productId' => 'p1', 'quantity' => '1', 'unitPrice' => '100']],
    ])->assertRedirect();

    $this->get(route('admin.reports.payments', ['from' => '2026-10-01', 'to' => '2026-10-01']))
        ->assertOk()
        ->assertSee('₱100.00');
});

it('rejects a future sale date', function (): void {
    seedProduct('p1');

    $this->post(route('admin.historical.store'), [
        'date' => '2026-10-07',
        'method' => 'cash',
        'lines' => [['productId' => 'p1', 'quantity' => '1']],
    ])->assertSessionHasErrors('date');

    expect(rtdb()->get('sales'))->toBeNull();
});

it('rejects a line whose product does not exist', function (): void {
    $this->post(route('admin.historical.store'), [
        'date' => '2026-10-01',
        'method' => 'cash',
        'lines' => [['productId' => 'nope', 'quantity' => '1']],
    ])->assertSessionHasErrors('lines.0.productId');

    expect(rtdb()->get('sales'))->toBeNull();
});

it('rejects a line with a zero quantity', function (): void {
    seedProduct('p1');

    $this->post(route('admin.historical.store'), [
        'date' => '2026-10-01',
        'method' => 'cash',
        'lines' => [['productId' => 'p1', 'quantity' => '0']],
    ])->assertSessionHasErrors('lines.0.quantity');

    expect(rtdb()->get('sales'))->toBeNull();
});

it('refuses the surface to a cashier', function (): void {
    seedStaff('maria@example.com', StaffRole::Cashier, 'Maria Santos');
    signIn('maria@example.com', 'Maria Santos');

    $this->get(route('admin.historical.create'))->assertForbidden();
    $this->post(route('admin.historical.store'), [])->assertForbidden();
});

it('redirects a signed-out visitor to sign in', function (): void {
    signOut();

    $this->get(route('admin.historical.create'))->assertRedirect(route('admin.login'));
});

it('hides the surface and the nav link when the feature is disabled', function (): void {
    seedProduct('p1');
    config(['settings.historical_sales_enabled' => false]);

    $this->get(route('admin.historical.create'))->assertNotFound();
    $this->post(route('admin.historical.store'), [])->assertNotFound();

    $this->get(route('admin.dashboard'))->assertOk()->assertDontSee('Encode sales');
});

it('shows the nav link while the feature is enabled', function (): void {
    seedProduct('p1');

    $this->get(route('admin.historical.create'))
        ->assertOk()
        ->assertSee('Encode sales')
        ->assertSee('Encode historical sales');
});

it('records the customer add-ons on a line and adds their prices to the total', function (): void {
    seedProduct('p1', [
        'name' => 'Milktea',
        'price' => 100.0,
        'addOns' => [
            ['name' => 'Extra pearls', 'price' => 20.0],
            ['name' => 'Less sugar', 'price' => 0.0],
        ],
    ]);

    $this->post(route('admin.historical.store'), [
        'date' => '2026-10-01',
        'method' => 'cash',
        'lines' => [[
            'productId' => 'p1',
            'quantity' => '2',
            'unitPrice' => '100',
            'addOns' => ['Extra pearls'],
        ]],
    ])->assertRedirect(route('admin.historical.create'));

    $sale = array_values(rtdb()->get('sales'))[0];

    // (100 base + 20 add-on) * 2 = 240.
    expect($sale['total'])->toBe(240.0)
        ->and($sale['lines'][0]['addOns'])->toBe([['name' => 'Extra pearls', 'price' => 20.0]]);

    // The reader the receipt uses picks the add-on straight back up.
    $this->get(route('admin.reports.sales', ['from' => '2026-10-01', 'to' => '2026-10-01']))
        ->assertOk()
        ->assertSee('₱240.00');
});

it('lists each product add-on as a pick on the encoding form', function (): void {
    seedProduct('p1', [
        'name' => 'Milktea',
        'addOns' => [['name' => 'Extra pearls', 'price' => 20.0]],
    ]);

    $this->get(route('admin.historical.create'))
        ->assertOk()
        // The picker JSON the script builds the checkboxes from, and the group.
        ->assertSee('product-addons', false)
        ->assertSee('Extra pearls')
        ->assertSee('line-row__addons', false);
});

it('rejects an add-on the product does not offer', function (): void {
    seedProduct('p1', [
        'addOns' => [['name' => 'Extra pearls', 'price' => 20.0]],
    ]);

    $this->post(route('admin.historical.store'), [
        'date' => '2026-10-01',
        'method' => 'cash',
        'lines' => [[
            'productId' => 'p1',
            'quantity' => '1',
            'addOns' => ['Free upgrade'],
        ]],
    ])->assertSessionHasErrors('lines.0.addOns.0');

    expect(rtdb()->get('sales'))->toBeNull();
});
