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
        ->assertSee('7-day trend')
        ->assertSee('Best sellers')
        // Best seller by units over the window: Adobo (3).
        ->assertSee('Adobo')
        ->assertSee('Peak hours')
        ->assertSee('10:00');
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

it('refuses the dashboard to a cashier and a signed-out visitor', function (): void {
    seedStaff('maria@example.com', StaffRole::Cashier, 'Maria Santos');

    signIn('maria@example.com', 'Maria Santos');
    $this->get(route('admin.dashboard'))->assertForbidden();

    signOut();
    $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
});
