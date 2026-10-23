<?php

declare(strict_types=1);

use App\Staff\StaffRole;

/**
 * #32 — admin reports: sales, reservations, and rental bookings with date
 * filters, plus the refunds-owed column and the admin-only Mark refunded.
 *
 * Each report reads its stream bounded by an indexed date field (`sales.soldAt`,
 * `reservations.date`, `rentalBookings.dateFrom`) and filters by the supplied
 * `from`/`to` range. The refunds-owed section lists the outstanding
 * obligations; Mark refunded clears the obligation on that order and is
 * refused to a cashier.
 */

beforeEach(function (): void {
    seedStaff('owner@example.com', StaffRole::Admin, 'Owner Santos');
    signIn('owner@example.com', 'Owner Santos');

    $this->travelTo(new DateTimeImmutable('2026-10-06 12:00:00', new DateTimeZone('Asia/Manila')));
});

it('filters the sales report by date', function (): void {
    seedSale('s1', ['code' => 'AAA1', 'total' => 100.0, 'soldAt' => '2026-10-06T04:00:00Z']);
    seedSale('s2', ['code' => 'BBB2', 'total' => 250.0, 'soldAt' => '2026-10-01T04:00:00Z']);

    $this->get(route('admin.reports.sales', ['from' => '2026-10-06', 'to' => '2026-10-06']))
        ->assertOk()
        ->assertSee('AAA1')
        ->assertDontSee('BBB2')
        ->assertSee('₱100.00');
});

it('filters the reservation report by date', function (): void {
    seedReservation('res1', ['date' => '2026-10-06', 'name' => 'Ana Cruz', 'phone' => '09170000001']);
    seedReservation('res2', ['date' => '2026-10-20', 'name' => 'Berto Reyes', 'phone' => '09170000002']);

    $this->get(route('admin.reports.reservations', ['from' => '2026-10-01', 'to' => '2026-10-10']))
        ->assertOk()
        ->assertSee('Ana Cruz')
        ->assertDontSee('Berto Reyes');
});

it('filters the rental-booking report by date and skips when rentals are absent', function (): void {
    // #34 owns the rental-booking shape. Until it lands, the report must read
    // defensively: a missing tree renders an empty state, never an error.
    $this->get(route('admin.reports.rentals', ['from' => '2026-10-01', 'to' => '2026-10-10']))
        ->assertOk()
        ->assertSee('Rental bookings');
});

it('lists the outstanding refunds with the claimed amount and a Mark refunded action', function (): void {
    seedAdminOrder('r1', ['code' => 'REJ1', 'refundOwed' => 350.0, 'rejectedAt' => '2026-10-06T03:00:00Z']);

    $this->get(route('admin.reports.sales', ['from' => '2026-10-01', 'to' => '2026-10-10']))
        ->assertOk()
        ->assertSee('Refunds owed')
        ->assertSee('REJ1')
        ->assertSee('₱350.00')
        ->assertSee('Mark refunded');
});

it('clears the obligation when an admin marks a refund refunded', function (): void {
    seedAdminOrder('r1', ['refundOwed' => 350.0]);

    $this->post(route('admin.reports.refunds.mark', ['order' => 'r1']))
        ->assertRedirect();

    $order = rtdb()->get('orders/r1');

    expect($order['refundOwed'])->toBeNull()
        ->and($order['refundedAt'])->toBeString()
        ->and($order['refundedBy'])->toBeArray();
});

it('drops a refunded obligation out of the refunds-owed list', function (): void {
    seedAdminOrder('r1', ['code' => 'REJ1', 'refundOwed' => 350.0]);

    $this->post(route('admin.reports.refunds.mark', ['order' => 'r1']));

    $this->get(route('admin.reports.sales', ['from' => '2026-10-01', 'to' => '2026-10-10']))
        ->assertOk()
        ->assertSee('No refunds owed — nothing outstanding.');
});

it('charts daily sales and lists every order in the range, including unsold ones', function (): void {
    seedSale('s1', ['code' => 'SOLD1', 'total' => 100.0, 'soldAt' => '2026-10-06T04:00:00Z']);
    // An order that never settled: it is in no sales row, so the report can
    // only show it from the orders tree.
    seedAdminOrder('o1', [
        'code' => 'OPEN1',
        'status' => 'awaiting_payment',
        'total' => 250.0,
        'placedAt' => '2026-10-06T05:00:00Z',
        'refundOwed' => null,
    ]);
    // An order outside the range must not leak in.
    seedAdminOrder('o2', [
        'code' => 'OUTSIDE',
        'status' => 'pending',
        'total' => 999.0,
        'placedAt' => '2026-09-01T05:00:00Z',
        'refundOwed' => null,
    ]);

    $this->get(route('admin.reports.sales', ['from' => '2026-10-06', 'to' => '2026-10-06']))
        ->assertOk()
        ->assertSee('Daily sales')
        ->assertSee('All orders')
        ->assertSee('SOLD1')
        ->assertSee('OPEN1')
        ->assertSee('Awaiting Payment')
        ->assertSee('₱250.00')
        ->assertDontSee('OUTSIDE')
        ->assertDontSee('999.00');
});

it('draws a GCash-versus-cash comparison chart on the payments report', function (): void {
    seedSale('w1', [
        'type' => 'walkin',
        'total' => 100.0,
        'payments' => [['method' => 'cash', 'amount' => 25.0], ['method' => 'gcash', 'amount' => 75.0]],
        'soldAt' => '2026-10-06T04:00:00Z',
    ]);

    $this->get(route('admin.reports.payments', ['from' => '2026-10-06', 'to' => '2026-10-06']))
        ->assertOk()
        ->assertSee('split-bar__segment--gcash', false)
        ->assertSee('split-bar__segment--cash', false)
        ->assertSee('GCash · ₱75.00 (75%)')
        ->assertSee('Cash · ₱25.00 (25%)');
});

it('refuses Mark refunded to a cashier', function (): void {
    seedStaff('maria@example.com', StaffRole::Cashier, 'Maria Santos');
    seedAdminOrder('r1', ['refundOwed' => 350.0]);

    signIn('maria@example.com', 'Maria Santos');

    $this->post(route('admin.reports.refunds.mark', ['order' => 'r1']))->assertForbidden();
    $this->get(route('admin.reports.sales'))->assertForbidden();

    expect(rtdb()->get('orders/r1')['refundOwed'])->toBe(350.0);
});

it('refuses the reports to a signed-out visitor', function (): void {
    signOut();

    $this->get(route('admin.reports.sales'))->assertRedirect(route('admin.login'));
    $this->post(route('admin.reports.refunds.mark', ['order' => 'r1']))->assertRedirect(route('admin.login'));
});

it('splits the sales ledger into GCash and cash totals on the payments report', function (): void {
    seedSale('w1', ['type' => 'walkin', 'total' => 100.0, 'payments' => [['method' => 'cash', 'amount' => 100.0]], 'soldAt' => '2026-10-06T04:00:00Z']);
    seedSale('w2', ['type' => 'walkin', 'total' => 200.0, 'payments' => [['method' => 'cash', 'amount' => 25.0], ['method' => 'gcash', 'amount' => 175.0]], 'soldAt' => '2026-10-06T04:10:00Z']);

    $this->get(route('admin.reports.payments', ['from' => '2026-10-06', 'to' => '2026-10-06']))
        ->assertOk()
        ->assertSee('Payments report')
        ->assertSee('₱125.00') // cash leg summed across the split tenders
        ->assertSee('₱175.00') // GCash leg
        ->assertSee('₱300.00'); // total
});

it('counts an online pickup as GCash in full on the payments report', function (): void {
    seedSale('w1', ['type' => 'walkin', 'total' => 100.0, 'payments' => [['method' => 'cash', 'amount' => 100.0]], 'soldAt' => '2026-10-06T04:00:00Z']);
    seedSale('o1', ['type' => 'online', 'total' => 400.0, 'payments' => [], 'soldAt' => '2026-10-06T05:00:00Z']);

    $this->get(route('admin.reports.payments', ['from' => '2026-10-06', 'to' => '2026-10-06']))
        ->assertOk()
        ->assertSee('₱400.00') // the online order's whole total on the GCash side
        ->assertSee('₱100.00') // the walk-in's cash
        ->assertSee('₱500.00'); // combined total
});

it('filters the payments report by date', function (): void {
    seedSale('in', ['type' => 'walkin', 'total' => 100.0, 'payments' => [['method' => 'cash', 'amount' => 100.0]], 'soldAt' => '2026-10-06T04:00:00Z']);
    seedSale('out', ['type' => 'walkin', 'total' => 999.0, 'payments' => [['method' => 'cash', 'amount' => 999.0]], 'soldAt' => '2026-10-01T04:00:00Z']);

    $this->get(route('admin.reports.payments', ['from' => '2026-10-06', 'to' => '2026-10-06']))
        ->assertOk()
        ->assertSee('₱100.00')
        ->assertDontSee('₱999.00');
});

it('excludes a rental booking from the payments totals', function (): void {
    seedSale('r1', ['type' => 'rental', 'total' => 777.0, 'payments' => [['method' => 'gcash', 'amount' => 777.0]], 'soldAt' => '2026-10-06T04:00:00Z']);

    $this->get(route('admin.reports.payments', ['from' => '2026-10-06', 'to' => '2026-10-06']))
        ->assertOk()
        ->assertSee('No payments in this range.')
        ->assertDontSee('₱777.00');
});

it('refuses the payments report to a cashier and a signed-out visitor', function (): void {
    seedStaff('maria@example.com', StaffRole::Cashier, 'Maria Santos');

    signIn('maria@example.com', 'Maria Santos');
    $this->get(route('admin.reports.payments'))->assertForbidden();

    signOut();
    $this->get(route('admin.reports.payments'))->assertRedirect(route('admin.login'));
});
