<?php

declare(strict_types=1);

use App\Staff\StaffDirectory;
use App\Staff\StaffRole;

/**
 * #32 — the admin Cashier history: which cashier accepted or rejected which
 * customer, for orders and rental bookings alike.
 *
 * It is built from the same stamps the Activity feed reads (`sales.handledBy`,
 * the order `rejection` record) plus the `handledBy` a rental booking carries
 * on a counter verdict. Every read is bounded (a `limitToLast` on an indexed
 * field), never a whole-tree scan.
 */

beforeEach(function (): void {
    seedStaff('owner@example.com', StaffRole::Admin, 'Owner Santos');
    signIn('owner@example.com', 'Owner Santos');

    // 2026-10-06 12:00 Asia/Manila is 04:00 UTC.
    $this->travelTo(new DateTimeImmutable('2026-10-06 12:00:00', new DateTimeZone('Asia/Manila')));
});

/**
 * A `handledBy` / rejection `by` node as the write stamps it.
 *
 * @return array{uid: string, name: string}
 */
function historyWho(string $email, string $name): array
{
    return ['uid' => StaffDirectory::idForEmail($email), 'name' => $name];
}

it('lists an accepted order handled by a cashier', function (): void {
    seedSale('s1', [
        'code' => 'AAA1',
        'type' => 'walkin',
        'total' => 200.0,
        'soldAt' => '2026-10-06T04:05:00Z',
        'updatedAt' => '2026-10-06T04:05:00Z',
        'handledBy' => historyWho('maria@example.com', 'Maria Santos'),
    ]);

    $this->get(route('admin.cashier.history'))
        ->assertOk()
        ->assertSee('Cashier history')
        ->assertSee('Maria Santos')
        ->assertSee('Accepted')
        ->assertSee('Order')
        ->assertSee('AAA1');
});

it('lists a rejected order with the claimed amount and the rejecting cashier', function (): void {
    seedAdminOrder('r1', [
        'code' => 'REJ1',
        'status' => 'rejected',
        'updatedAt' => '2026-10-06T04:10:00Z',
        'rejection' => [
            'orderId' => 'r1',
            'enteredAmount' => 350.0,
            'orderTotal' => 400.0,
            'ref' => 'GC999',
            'by' => historyWho('jose@example.com', 'Jose Rizal'),
            'at' => '2026-10-06T04:10:00Z',
        ],
    ]);

    $this->get(route('admin.cashier.history'))
        ->assertOk()
        ->assertSee('Jose Rizal')
        ->assertSee('Rejected')
        ->assertSee('REJ1')
        ->assertSee('Claimed ₱350.00 of ₱400.00');
});

it('lists a cancelled booking as a rejection and a returned booking as an acceptance', function (): void {
    seedRentalBooking('b1', [
        'code' => 'RNT-REJ',
        'status' => 'cancelled',
        'updatedAt' => '2026-10-06T04:20:00Z',
        'cancelledAt' => '2026-10-06T04:20:00Z',
        'handledBy' => historyWho('maria@example.com', 'Maria Santos'),
    ]);
    seedRentalBooking('b2', [
        'code' => 'RNT-OK',
        'status' => 'returned',
        'updatedAt' => '2026-10-06T04:30:00Z',
        'returnedAt' => '2026-10-06T04:30:00Z',
        'handledBy' => historyWho('jose@example.com', 'Jose Rizal'),
    ]);

    $this->get(route('admin.cashier.history'))
        ->assertOk()
        ->assertSee('RNT-REJ')
        ->assertSee('RNT-OK')
        ->assertSee('Booking')
        ->assertSee('Rejected')
        ->assertSee('Accepted');
});

it('ignores a booking with no counter decision', function (): void {
    seedRentalBooking('b1', [
        'code' => 'RNT-WAIT',
        'status' => 'awaiting_payment',
        'updatedAt' => '2026-10-06T04:20:00Z',
        'handledBy' => historyWho('maria@example.com', 'Maria Santos'),
    ]);

    $this->get(route('admin.cashier.history'))
        ->assertOk()
        ->assertDontSee('RNT-WAIT');
});

it('refuses the Cashier history to a cashier', function (): void {
    seedStaff('maria@example.com', StaffRole::Cashier, 'Maria Santos');

    signIn('maria@example.com', 'Maria Santos');

    $this->get(route('admin.cashier.history'))->assertForbidden();
});
