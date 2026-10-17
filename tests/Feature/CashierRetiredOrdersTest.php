<?php

declare(strict_types=1);

use App\Orders\OrderStatus;
use App\Orders\OrderTracker;
use App\Staff\StaffDirectory;
use App\Staff\StaffRole;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * #29 — retired orders: the Dismissed list (Restore / Void), the ready —
 * awaiting handover row, and Unclaimed → Collect late.
 *
 * A Dismissed order (the window lapsed with zero proof) can be Restored — a
 * fresh 15-minute window, the tracker flipped back to awaiting payment, and an
 * audit entry (order id, who, when), unlimited times — or Voided, which ends
 * the order and never clears `refundOwed`. Tickets the server clears off the
 * kitchen board land in the ready — awaiting handover row. A paid but never
 * collected Unclaimed order gains Collect late: it flips to served and writes
 * no second sale, because the close sweep synthesized `sales/{id}` already.
 * Every tap is status-guarded, so a stale or duplicate tap is a friendly no-op.
 */

beforeEach(function (): void {
    seedStaff('maria@example.com', StaffRole::Cashier, 'Maria Santos');
    signIn('maria@example.com', 'Maria Santos');

    // 2026-10-06 12:00 Asia/Manila is 04:00 UTC — the windows below straddle it.
    $this->travelTo(new DateTimeImmutable('2026-10-06 12:00:00', new DateTimeZone('Asia/Manila')));
});

/**
 * Arrange an online order exactly as #23/#24/#25 write it.
 *
 * @param  array<string, mixed>  $overrides
 */
function seedRetiredOrder(array $overrides = []): string
{
    $id = (string) Str::ulid();

    rtdb()->seed("orders/{$id}", [
        'code' => 'RET1',
        'type' => 'online',
        'status' => OrderStatus::Dismissed->value,
        'lines' => [],
        'total' => 200.0,
        'payments' => [],
        'change' => 0.0,
        'customerUid' => 'sub-ada',
        'placedAt' => '2026-10-06T04:00:00Z',
        'pickupAt' => null,
        'deadlineAt' => '2026-10-06T04:15:00Z',
        ...$overrides,
    ]);

    return $id;
}

function restoreOrder(string $id): TestResponse
{
    return test()->post(route('cashier.orders.restore', ['order' => $id]));
}

function voidRetiredOrder(string $id): TestResponse
{
    return test()->post(route('cashier.orders.void', ['order' => $id]));
}

function collectLateOrder(string $id): TestResponse
{
    return test()->post(route('cashier.orders.collect-late', ['order' => $id]));
}

/**
 * The cashier's coalesced queue state, decoded.
 *
 * @return array<string, mixed>
 */
function queueState(): array
{
    return test()->get(route('cashier.queue.state'))->assertOk()->json();
}

// --- Dismissed list: Restore / Void ---------------------------------------

it('lists a dismissed order with Restore and Void', function (): void {
    seedRetiredOrder(['code' => 'DISM1']);

    $this->get(route('cashier.queue'))
        ->assertOk()
        ->assertSee('Dismissed')
        ->assertSee('DISM1')
        ->assertSee('Restore')
        ->assertSee('Void');
});

it('restores a dismissed order: a fresh 15-minute window, the tracker back, and an audit entry', function (): void {
    $id = seedRetiredOrder([
        'code' => 'REST1',
        'dismissedAt' => '2026-10-06T03:59:00Z',
        'deadlineAt' => '2026-10-06T03:00:00Z',
    ]);

    restoreOrder($id)->assertRedirect(route('cashier.queue'));

    $order = rtdb()->get("orders/{$id}");

    expect($order['status'])->toBe(OrderStatus::AwaitingPayment->value)
        // A fresh window: now (04:00 UTC) + 15 minutes, not the old deadline.
        ->and($order['deadlineAt'])->toBe('2026-10-06T04:15:00Z');

    // The customer's tracker flips back to awaiting payment.
    expect(app(OrderTracker::class)->state($id)?->status)->toBe(OrderStatus::AwaitingPayment->value);

    $entry = collect(rtdb()->get('audit'))->firstWhere('orderId', $id);

    expect($entry)->toBeArray()
        ->and($entry['action'])->toBe('restore')
        ->and($entry['who'])->toBe(['uid' => StaffDirectory::idForEmail('maria@example.com'), 'name' => 'Maria Santos'])
        ->and($entry['when'])->toBeString();
});

it('restores unlimited times, each writing its own audit entry', function (): void {
    $id = seedRetiredOrder([
        'code' => 'MANY1',
        'dismissedAt' => '2026-10-06T03:59:00Z',
        'deadlineAt' => '2026-10-06T03:00:00Z',
    ]);

    restoreOrder($id)->assertRedirect(route('cashier.queue'));

    // The fresh window lapses and the order is dismissed a second time — a real
    // lapse, not a bug — then restored again.
    $this->travel(16)->minutes();
    $this->artisan('orders:expire')->assertSuccessful();

    expect(rtdb()->get("orders/{$id}")['status'])->toBe(OrderStatus::Dismissed->value);

    restoreOrder($id)->assertRedirect(route('cashier.queue'));

    expect(collect(rtdb()->get('audit'))->where('orderId', $id))->toHaveCount(2)
        ->and(rtdb()->get("orders/{$id}")['status'])->toBe(OrderStatus::AwaitingPayment->value);
});

it('treats a stale Restore on a non-dismissed order as a friendly no-op', function (): void {
    $id = seedRetiredOrder([
        'code' => 'STALE1',
        'status' => OrderStatus::Cooking->value,
        'deadlineAt' => '2026-10-06T04:15:00Z',
    ]);

    restoreOrder($id)->assertRedirect(route('cashier.queue'));

    expect(rtdb()->get("orders/{$id}")['status'])->toBe(OrderStatus::Cooking->value)
        ->and(rtdb()->get("orders/{$id}")['deadlineAt'])->toBe('2026-10-06T04:15:00Z')
        ->and(rtdb()->get('audit'))->toBeNull();
});

it('never offers Restore on a Rejected order', function (): void {
    $id = seedRetiredOrder([
        'code' => 'REJ1',
        'status' => OrderStatus::Rejected->value,
        'refundOwed' => 150.0,
        'rejection' => ['enteredAmount' => 150.0, 'total' => 200.0, 'ref' => 'GC999'],
    ]);

    // Rejected is its own state (#27); it is never a member of the Dismissed list.
    expect(collect(queueState()['dismissed'])->pluck('id'))->not->toContain($id);

    restoreOrder($id)->assertRedirect(route('cashier.queue'));

    $order = rtdb()->get("orders/{$id}");

    expect($order['status'])->toBe(OrderStatus::Rejected->value)
        ->and($order['deadlineAt'])->toBe('2026-10-06T04:15:00Z')
        ->and(rtdb()->get('audit'))->toBeNull();
});

it('voids an order and never clears refundOwed', function (): void {
    $id = seedRetiredOrder([
        'code' => 'VOID1',
        'refundOwed' => 150.0,
    ]);

    voidRetiredOrder($id)->assertRedirect(route('cashier.queue'));

    $order = rtdb()->get("orders/{$id}");

    expect($order['status'])->toBe(OrderStatus::Voided->value)
        ->and($order['refundOwed'])->toBe(150.0)
        ->and($order['voidedAt'])->toBeString();
});

it('treats a stale Void on a non-dismissed order as a friendly no-op', function (): void {
    $id = seedRetiredOrder(['code' => 'VOID2', 'status' => OrderStatus::Cooking->value]);

    voidRetiredOrder($id)->assertRedirect(route('cashier.queue'));

    expect(rtdb()->get("orders/{$id}")['status'])->toBe(OrderStatus::Cooking->value);
});

it('drops a voided order out of the Dismissed list', function (): void {
    $id = seedRetiredOrder(['code' => 'VOID3']);

    voidRetiredOrder($id)->assertRedirect(route('cashier.queue'));

    $this->get(route('cashier.queue'))->assertOk()->assertDontSee('VOID3');
});

// --- Ready — awaiting handover --------------------------------------------

it('holds server-cleared tickets in the ready — awaiting handover row', function (): void {
    // A scheduled pickup whose ready-for + 15 minutes has passed: the server
    // has cleared it off the board, so it waits in the counter row.
    $id = seedRetiredOrder([
        'code' => 'HAND1',
        'status' => OrderStatus::Cooking->value,
        'pickupAt' => '2026-10-06T03:30:00Z',
        'proofReceivedAt' => '2026-10-06T03:00:00Z',
    ]);

    $this->get(route('cashier.queue'))
        ->assertOk()
        ->assertSee('Ready — awaiting handover')
        ->assertSee('HAND1');

    expect(collect(queueState()['awaitingHandover'])->pluck('id'))->toContain($id);
});

it('keeps a ticket still inside its ready-for window out of the handover row', function (): void {
    $id = seedRetiredOrder([
        'code' => 'FRESH1',
        'status' => OrderStatus::Cooking->value,
        'pickupAt' => '2026-10-06T04:30:00Z',
        'proofReceivedAt' => '2026-10-06T03:30:00Z',
    ]);

    $state = queueState();

    expect(collect($state['awaitingHandover'])->pluck('id'))->not->toContain($id)
        ->and(collect($state['readyToPay'])->pluck('id'))->toContain($id);
});

// --- Unclaimed: Collect late ----------------------------------------------

it('collects late an Unclaimed order without writing a second sale', function (): void {
    $id = seedRetiredOrder([
        'code' => 'LATE1',
        'status' => OrderStatus::Unclaimed->value,
        'unclaimedAt' => '2026-10-06T03:50:00Z',
    ]);

    // The close sweep synthesized the 1:1 sale at close.
    rtdb()->seed("sales/{$id}", [
        'code' => 'LATE1',
        'type' => 'online',
        'total' => 200.0,
        'payments' => [['method' => 'gcash', 'amount' => 200.0]],
        'soldAt' => '2026-10-06T03:50:00Z',
    ]);

    collectLateOrder($id)->assertRedirect(route('cashier.queue'));

    $order = rtdb()->get("orders/{$id}");

    expect($order['status'])->toBe(OrderStatus::Served->value)
        ->and($order['handledBy'])->toBe(['uid' => StaffDirectory::idForEmail('maria@example.com'), 'name' => 'Maria Santos']);

    $sale = rtdb()->get("sales/{$id}");

    // No second sale write: the synthesized row is untouched (no fresh
    // `updatedAt`, same `soldAt`).
    expect($sale['soldAt'])->toBe('2026-10-06T03:50:00Z')
        ->and($sale)->not->toHaveKey('updatedAt');
});

it('treats a stale Collect late on an order that is not Unclaimed as a friendly no-op', function (): void {
    $id = seedRetiredOrder(['code' => 'LATE2', 'status' => OrderStatus::Cooking->value]);

    collectLateOrder($id)->assertRedirect(route('cashier.queue'));

    expect(rtdb()->get("orders/{$id}")['status'])->toBe(OrderStatus::Cooking->value);
});

it('drops a collected-late order out of the Unclaimed list', function (): void {
    $id = seedRetiredOrder(['code' => 'LATE3', 'status' => OrderStatus::Unclaimed->value]);

    rtdb()->seed("sales/{$id}", [
        'code' => 'LATE3',
        'type' => 'online',
        'total' => 200.0,
        'payments' => [['method' => 'gcash', 'amount' => 200.0]],
        'soldAt' => '2026-10-06T03:50:00Z',
    ]);

    collectLateOrder($id)->assertRedirect(route('cashier.queue'));

    expect(collect(queueState()['unclaimed'])->pluck('id'))->not->toContain($id);
});

it('refuses a customer at the retired-order taps', function (): void {
    auth()->logout();
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');

    $id = seedRetiredOrder(['code' => 'GUARD1']);

    restoreOrder($id)->assertForbidden();
    voidRetiredOrder($id)->assertForbidden();
    collectLateOrder($id)->assertForbidden();
});
