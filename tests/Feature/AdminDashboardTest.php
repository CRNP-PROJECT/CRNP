<?php

declare(strict_types=1);

use App\Staff\StaffRole;

/**
 * #32 — the admin dashboard.
 *
 * The dashboard shows the diner's KPIs, a monthly GCash/cash payment mix, a
 * 7-day sales trend, best-sellers, peak hours, and a refunds-owed badge counting
 * the outstanding `refundOwed`. Everything is computed server-side from the
 * sales ledger (bounded by `soldAt`) and the audit/order rows; rental bookings
 * are excluded from the product ranking and the trend.
 */

beforeEach(function (): void {
    seedStaff('owner@example.com', StaffRole::Admin, 'Owner Santos');
    signIn('owner@example.com', 'Owner Santos');

    // 2026-10-06 12:00 Asia/Manila is 04:00 UTC.
    $this->travelTo(new DateTimeImmutable('2026-10-06 12:00:00', new DateTimeZone('Asia/Manila')));
});

/**
 * One sale line, exactly as the ledger freezes it.
 *
 * @return array{productId: string, name: string, unitPrice: float, quantity: int, addOns: list<array{name: string, price: float}>}
 */
function dashboardLine(string $productId, string $name, float $unitPrice, int $quantity): array
{
    return [
        'productId' => $productId,
        'name' => $name,
        'unitPrice' => $unitPrice,
        'quantity' => $quantity,
        'addOns' => [],
    ];
}

/**
 * Arrange a sale row on the ledger.
 *
 * @param  list<array<string, mixed>>  $lines
 * @param  array<string, mixed>  $overrides
 */
function dashboardSale(string $id, string $soldAt, array $lines, float $total, array $overrides = []): string
{
    return seedSale($id, [
        'code' => strtoupper($id),
        'type' => 'walkin',
        'lines' => $lines,
        'total' => $total,
        'soldAt' => $soldAt,
        ...$overrides,
    ]);
}

it('shows the KPIs, the 7-day trend, best-sellers, and peak hours', function (): void {
    dashboardSale('a1', '2026-10-06T02:00:00Z', [dashboardLine('p1', 'Milktea', 100.0, 2)], 200.0);
    dashboardSale('a2', '2026-10-06T03:00:00Z', [dashboardLine('p2', 'Adobo', 100.0, 3)], 300.0);
    dashboardSale('a3', '2026-10-05T04:00:00Z', [dashboardLine('p3', 'Halo-halo', 100.0, 1)], 100.0);

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Sales today')
        ->assertSee('₱500.00')
        ->assertSee('Orders today')
        ->assertSee('Sales · last 7 days')
        ->assertSee('Best sellers · last 7 days')
        // Best seller by units over the window: Adobo (3).
        ->assertSee('Adobo')
        // The per-day chart: a spark row for each ranked product.
        ->assertSee('Best sellers per day · last 7 days')
        ->assertSee('spark__bar', false)
        ->assertSee('hbar__fill', false)
        ->assertSee('Peak hours')
        ->assertSee('10:00');
});

it('renders the mobile nav toggle so the admin header folds on a phone', function (): void {
    $this->get(route('admin.dashboard'))
        ->assertOk()
        // The hidden checkbox, its hamburger label, and the drawer wrapper the
        // narrow-screen rules collapse the nav into.
        ->assertSee('site-nav-toggle', false)
        ->assertSee('id="admin-nav-toggle"', false)
        ->assertSee('site-menu', false)
        // The nav and the sign-out sit inside the drawer, not loose in the header.
        ->assertSee('Sign out');
});

it('downloads the last backup file', function (): void {
    \Illuminate\Support\Facades\Storage::fake('local');
    \Illuminate\Support\Facades\Storage::disk('local')->put('exports/backup.json', '{"sales":[]}');

    rtdb()->seed('export/state', [
        'lastBackupAt' => '2026-10-05T16:00:00Z',
        'lastBackupPath' => 'exports/backup.json',
    ]);

    $this->get(route('admin.backup.download'))->assertOk()->assertDownload('backup.json');
});

it('returns 404 when no backup exists yet', function (): void {
    \Illuminate\Support\Facades\Storage::fake('local');

    $this->get(route('admin.backup.download'))->assertNotFound();
});

it('shows a refunds-owed badge counting the outstanding obligations', function (): void {
    seedAdminOrder('r1', ['refundOwed' => 350.0, 'rejectedAt' => '2026-10-06T03:00:00Z']);
    seedAdminOrder('r2', ['refundOwed' => 150.0, 'status' => 'voided', 'rejectedAt' => '2026-10-06T03:00:00Z']);

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Refunds owed')
        ->assertSee('₱500.00')
        ->assertSee('2');
});

it('excludes a rental booking from the 7-day product trend and best-sellers', function (): void {
    // A rental sale type is not an order sale; its 9-unit line and ₱4,500 total
    // must not reach the product ranking or the trend.
    seedSale('rent1', [
        'code' => 'RENT1',
        'type' => 'rental',
        'lines' => [dashboardLine('p9', 'Party Tent', 500.0, 9)],
        'total' => 4500.0,
        'soldAt' => '2026-10-06T02:00:00Z',
    ]);
    dashboardSale('o1', '2026-10-06T02:00:00Z', [dashboardLine('p1', 'Milktea', 100.0, 1)], 100.0);

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Milktea')
        ->assertDontSee('Party Tent')
        // Today's KPI is the order only, never the rental booking.
        ->assertSee('₱100.00')
        ->assertDontSee('4,500.00');
});

it('shows the monthly payment mix, separating GCash from cash', function (): void {
    // A walk-in split tender: ₱25 cash + ₱175 GCash.
    dashboardSale('m1', '2026-10-06T02:00:00Z', [], 200.0, [
        'payments' => [['method' => 'cash', 'amount' => 25.0], ['method' => 'gcash', 'amount' => 175.0]],
    ]);
    // An online pickup: prepaid GCash in full, no tender legs on the row.
    dashboardSale('m2', '2026-10-06T03:00:00Z', [], 400.0, [
        'type' => 'online',
        'payments' => [],
    ]);

    $this->get(route('admin.dashboard'))
        ->assertOk()
        // The current business month titles every card.
        ->assertSee('GCash · October 2026')
        ->assertSee('Cash · pay at the counter · October 2026')
        ->assertSee('Total · October 2026')
        ->assertSee('₱575.00') // GCash: ₱175 walk-in leg + ₱400 online
        ->assertSee('₱25.00')  // cash · pay at the counter
        ->assertSee('₱600.00') // total
        // The stepper offers the neighbouring business months.
        ->assertSee('September 2026')
        ->assertSee('November 2026');
});

it('charts how many orders were paid by cash and by GCash', function (): void {
    // Cash only.
    dashboardSale('t1', '2026-10-06T02:00:00Z', [], 200.0, [
        'payments' => [['method' => 'cash', 'amount' => 200.0]],
    ]);
    // GCash only.
    dashboardSale('t2', '2026-10-06T03:00:00Z', [], 150.0, [
        'payments' => [['method' => 'gcash', 'amount' => 150.0]],
    ]);
    // Online: GCash in full.
    dashboardSale('t3', '2026-10-06T04:00:00Z', [], 400.0, [
        'type' => 'online',
        'payments' => [],
    ]);
    // Split tender: counts on both sides.
    dashboardSale('t4', '2026-10-06T05:00:00Z', [], 100.0, [
        'payments' => [['method' => 'cash', 'amount' => 50.0], ['method' => 'gcash', 'amount' => 50.0]],
    ]);

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Cash vs GCash · orders · last 7 days')
        ->assertSee('trend__bar-wrap--pair', false)
        // Cash: t1 + t4. GCash: t2 + t3 + t4, all on 6 Oct.
        ->assertSee('title="Cash 2 · GCash 3"', false)
        ->assertSee('Sales · last 7 days')
        ->assertSee('Orders · last 7 days');
});

it('filters the monthly payment mix by the chosen month', function (): void {
    dashboardSale('aug', '2026-08-15T02:00:00Z', [], 111.0, [
        'payments' => [['method' => 'cash', 'amount' => 111.0]],
    ]);
    dashboardSale('sep', '2026-09-15T02:00:00Z', [], 222.0, [
        'payments' => [['method' => 'cash', 'amount' => 222.0]],
    ]);

    $this->get(route('admin.dashboard', ['month' => '2026-08']))
        ->assertOk()
        ->assertSee('Total · August 2026')
        ->assertSee('₱111.00')
        ->assertDontSee('₱222.00');

    $this->get(route('admin.dashboard', ['month' => '2026-09']))
        ->assertOk()
        ->assertSee('Total · September 2026')
        ->assertSee('₱222.00')
        ->assertDontSee('₱111.00');
});

it('shows an empty state when the chosen month has no payments', function (): void {
    $this->get(route('admin.dashboard', ['month' => '2026-07']))
        ->assertOk()
        ->assertSee('No payments in July 2026.');
});

it('shows the monthly booking mix, paid bookings only, GCash and cash split', function (): void {
    // A paid booking (confirmed) with no tender legs: prepaid GCash in full.
    seedRentalBooking('b1', [
        'code' => 'RNT1',
        'status' => 'confirmed',
        'dateFrom' => '2026-10-08',
        'total' => 150.0,
        'payments' => [],
    ]);
    // A handed-over booking with a split tender: ₱50 cash + ₱50 GCash.
    seedRentalBooking('b2', [
        'code' => 'RNT2',
        'status' => 'handed_over',
        'dateFrom' => '2026-10-09',
        'total' => 100.0,
        'payments' => [['method' => 'cash', 'amount' => 50.0], ['method' => 'gcash', 'amount' => 50.0]],
    ]);
    // An unpaid window must never reach the mix.
    seedRentalBooking('b3', [
        'code' => 'RNT3',
        'status' => 'awaiting_payment',
        'dateFrom' => '2026-10-10',
        'total' => 999.0,
        'payments' => [],
    ]);

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Booking GCash · October 2026')
        ->assertSee('Booking cash · October 2026')
        ->assertSee('Booking total · October 2026')
        ->assertSee('₱200.00') // booking GCash: ₱150 prepaid + ₱50 leg
        ->assertSee('₱50.00')  // booking cash leg
        ->assertSee('₱250.00') // booking total
        ->assertDontSee('999.00');
});

it('excludes a booking whose rental period starts in another month', function (): void {
    seedRentalBooking('b1', [
        'code' => 'RNT-SEP',
        'status' => 'confirmed',
        'dateFrom' => '2026-09-15',
        'dateTo' => '2026-09-17',
        'total' => 444.0,
        'payments' => [],
    ]);

    $this->get(route('admin.dashboard', ['month' => '2026-10']))
        ->assertOk()
        ->assertSee('No paid bookings in October 2026.')
        ->assertDontSee('444.00');
});

it('shows a per-day daily-collections table splitting orders and bookings by GCash and cash', function (): void {
    // Orders on the 6th: a walk-in split ₱25 cash + ₱175 GCash, and an online
    // pickup of ₱400 as GCash in full.
    dashboardSale('d1', '2026-10-06T02:00:00Z', [], 200.0, [
        'payments' => [['method' => 'cash', 'amount' => 25.0], ['method' => 'gcash', 'amount' => 175.0]],
    ]);
    dashboardSale('d2', '2026-10-06T03:00:00Z', [], 400.0, [
        'type' => 'online',
        'payments' => [],
    ]);
    // An order on the 7th: all cash ₱60.
    dashboardSale('d3', '2026-10-07T02:00:00Z', [], 60.0, [
        'payments' => [['method' => 'cash', 'amount' => 60.0]],
    ]);

    // A paid booking on the 8th: prepaid GCash in full.
    seedRentalBooking('db1', [
        'code' => 'RNT-DB1',
        'status' => 'confirmed',
        'dateFrom' => '2026-10-08',
        'total' => 150.0,
        'payments' => [],
    ]);
    // An unpaid window on the 9th must never appear.
    seedRentalBooking('db2', [
        'code' => 'RNT-DB2',
        'status' => 'awaiting_payment',
        'dateFrom' => '2026-10-09',
        'total' => 999.0,
        'payments' => [],
    ]);

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Daily collections · October 2026')
        ->assertSee('Orders · GCash')
        ->assertSee('Bookings · Cash')
        // Day 6 orders: GCash ₱575 (₱175 + ₱400), cash ₱25.
        ->assertSee('₱575.00')
        ->assertSee('₱25.00')
        // Day 7 order: cash ₱60.
        ->assertSee('₱60.00')
        // Day 8 booking: GCash ₱150.
        ->assertSee('₱150.00')
        // The unpaid booking never lands.
        ->assertDontSee('999.00');
});

it('refuses the dashboard to a cashier and a signed-out visitor', function (): void {
    seedStaff('maria@example.com', StaffRole::Cashier, 'Maria Santos');

    signIn('maria@example.com', 'Maria Santos');
    $this->get(route('admin.dashboard'))->assertForbidden();

    signOut();
    $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
});
