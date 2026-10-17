<?php

declare(strict_types=1);

use App\Staff\StaffDirectory;
use App\Staff\StaffRole;

/**
 * #32 — the Activity feed: time · who · action · order code, latest N and
 * bounded, plus per-cashier daily totals grouped by `handledBy`.
 *
 * The feed is built from the audit records (#29 Restore) and the `handledBy`
 * stamps on the sales ledger — the field rides on the sale, so the totals need
 * no extra reads. Every read is bounded (a `limitToLast` on an indexed field),
 * never a whole-tree scan.
 */

beforeEach(function (): void {
    seedStaff('owner@example.com', StaffRole::Admin, 'Owner Santos');
    signIn('owner@example.com', 'Owner Santos');

    // 2026-10-06 12:00 Asia/Manila is 04:00 UTC.
    $this->travelTo(new DateTimeImmutable('2026-10-06 12:00:00', new DateTimeZone('Asia/Manila')));
});

/**
 * A `handledBy` node as the sale stamps it.
 *
 * @return array{uid: string, name: string}
 */
function activityWho(string $email, string $name): array
{
    return ['uid' => StaffDirectory::idForEmail($email), 'name' => $name];
}

it('shows the Activity feed with time, who, action, and order code', function (): void {
    seedSale('s1', [
        'code' => 'AAA1',
        'type' => 'online',
        'total' => 200.0,
        'soldAt' => '2026-10-06T04:05:00Z',
        'updatedAt' => '2026-10-06T04:05:00Z',
        'handledBy' => activityWho('maria@example.com', 'Maria Santos'),
    ]);

    $this->get(route('admin.activity'))
        ->assertOk()
        ->assertSee('Activity')
        ->assertSee('Maria Santos')
        ->assertSee('Mark served')
        ->assertSee('AAA1')
        ->assertSee('2026-10-06');
});

it('includes a Restore audit entry with its order code', function (): void {
    seedAdminOrder('r1', ['code' => 'REST1', 'status' => 'awaiting_payment', 'refundOwed' => null]);

    rtdb()->seed('audit/entry1', [
        'orderId' => 'r1',
        'code' => 'REST1',
        'action' => 'restore',
        'who' => activityWho('maria@example.com', 'Maria Santos'),
        'when' => '2026-10-06T04:06:00Z',
        'updatedAt' => '2026-10-06T04:06:00Z',
    ]);

    $this->get(route('admin.activity'))
        ->assertOk()
        ->assertSee('Restore')
        ->assertSee('REST1')
        ->assertSee('Maria Santos');
});

it('groups per-cashier daily totals by handledBy', function (): void {
    seedSale('s1', [
        'code' => 'AAA1',
        'type' => 'walkin',
        'total' => 200.0,
        'soldAt' => '2026-10-06T04:05:00Z',
        'updatedAt' => '2026-10-06T04:05:00Z',
        'handledBy' => activityWho('maria@example.com', 'Maria Santos'),
    ]);
    seedSale('s2', [
        'code' => 'BBB2',
        'type' => 'walkin',
        'total' => 300.0,
        'soldAt' => '2026-10-06T05:05:00Z',
        'updatedAt' => '2026-10-06T05:05:00Z',
        'handledBy' => activityWho('maria@example.com', 'Maria Santos'),
    ]);
    seedSale('s3', [
        'code' => 'CCC3',
        'type' => 'walkin',
        'total' => 50.0,
        'soldAt' => '2026-10-06T06:05:00Z',
        'updatedAt' => '2026-10-06T06:05:00Z',
        'handledBy' => activityWho('jose@example.com', 'Jose Rizal'),
    ]);

    $this->get(route('admin.activity'))
        ->assertOk()
        ->assertSee('Maria Santos')
        ->assertSee('₱500.00')
        ->assertSee('Jose Rizal')
        ->assertSee('₱50.00');
});

it('bounds the Activity feed to the latest entries', function (): void {
    for ($i = 0; $i < 30; $i++) {
        $minute = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
        seedSale("s{$i}", [
            'code' => "CODE{$i}",
            'type' => 'walkin',
            'total' => 10.0,
            'soldAt' => "2026-10-06T04:{$minute}:00Z",
            'updatedAt' => "2026-10-06T04:{$minute}:00Z",
            'handledBy' => activityWho('maria@example.com', 'Maria Santos'),
        ]);
    }

    $html = $this->get(route('admin.activity'))->assertOk()->getContent();

    expect($html)->toBeString()
        // The newest entry is shown; an entry far outside the bounded window is
        // not (the feed reads a bounded `limitToLast`, never the whole tree).
        ->and($html)->toContain('CODE29')
        ->and($html)->not->toContain('CODE0<');
});

it('refuses the Activity feed to a cashier', function (): void {
    seedStaff('maria@example.com', StaffRole::Cashier, 'Maria Santos');

    signIn('maria@example.com', 'Maria Santos');

    $this->get(route('admin.activity'))->assertForbidden();
});
