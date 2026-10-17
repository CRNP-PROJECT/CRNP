<?php

declare(strict_types=1);

use App\Orders\OrderStatus;
use App\Staff\StaffDirectory;
use App\Staff\StaffRole;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * #27 — the Rejected state: Reject, refund owed, Approve (the undo), Void.
 *
 * A cashier rejects a flagged hold (or a spot-checked verified order): the
 * rejection record is written (order id, entered vs total, ref#, who, when),
 * the image is kept 7 days as evidence, and `refundOwed` is booked as the
 * **claimed** (entered) amount. The ticket leaves the board the same instant —
 * the board mirrors verified, unserved — and the customer's tracker reads
 * "Payment needs checking — see the counter" with both numbers. From Rejected
 * the only actions are **Approve** (the undo — the ticket rejoins NOW by age,
 * the timer is never restarted and the NEW badge never reused) or **Void**
 * (ends the order and never clears `refundOwed`); **Restore is never offered**.
 * The close sweep leaves a Rejected order alone.
 */

beforeEach(function (): void {
    seedStaff('maria@example.com', StaffRole::Cashier, 'Maria Santos');
    signIn('maria@example.com', 'Maria Santos');

    // 2026-10-06 12:00 Asia/Manila is 04:00 UTC — the order instants below
    // straddle this instant.
    $this->travelTo(new DateTimeImmutable('2026-10-06 12:00:00', new DateTimeZone('Asia/Manila')));
});

/**
 * Arrange an online order a cashier can still act on, exactly as #23/#24 write
 * it: the default is a **flagged hold** (`verifying`) carrying the entered
 * amount and ref#.
 *
 * @param  array<string, mixed>  $overrides
 */
function seedCashierOrder(array $overrides = []): string
{
    $id = (string) Str::ulid();

    rtdb()->seed("orders/{$id}", [
        'code' => 'REJ1',
        'type' => 'online',
        'status' => OrderStatus::Verifying->value,
        'lines' => [],
        'total' => 400.0,
        'payments' => [],
        'change' => 0.0,
        'customerUid' => 'sub-ada',
        'placedAt' => '2026-10-06T03:50:00Z',
        'pickupAt' => null,
        'deadlineAt' => '2026-10-06T04:15:00Z',
        'enteredAmount' => 350.0,
        'gcashRef' => 'GC999',
        'proofReceivedAt' => '2026-10-06T03:55:00Z',
        'flaggedAt' => '2026-10-06T03:55:00Z',
        ...$overrides,
    ]);

    return $id;
}

function rejectOrder(string $id): TestResponse
{
    return test()->post(route('cashier.orders.reject', ['order' => $id]));
}

function approveOrder(string $id): TestResponse
{
    return test()->post(route('cashier.orders.approve', ['order' => $id]));
}

function voidOrder(string $id): TestResponse
{
    return test()->post(route('cashier.orders.void', ['order' => $id]));
}

// --- Reject ---------------------------------------------------------------

it('writes the rejection record, books refundOwed as the claimed amount, and keeps the image', function (): void {
    $id = seedCashierOrder();
    rtdb()->seed("proofs/{$id}", [
        'orderId' => $id,
        'imageBase64' => 'data:image/jpeg;base64,PROOFBYTES',
        'amount' => 350.0,
        'ref' => 'GC999',
    ]);

    rejectOrder($id)->assertRedirect(route('cashier.queue'));

    $order = rtdb()->get("orders/{$id}");

    expect($order['status'])->toBe(OrderStatus::Rejected->value)
        ->and($order['rejectedAt'])->toBeString()
        ->and($order['refundOwed'])->toBe(350.0)
        ->and($order['rejection'])->toBeArray()
        ->and($order['rejection']['orderId'])->toBe($id)
        ->and($order['rejection']['enteredAmount'])->toBe(350.0)
        ->and($order['rejection']['orderTotal'])->toBe(400.0)
        ->and($order['rejection']['ref'])->toBe('GC999')
        ->and($order['rejection']['by'])->toBe([
            'uid' => StaffDirectory::idForEmail('maria@example.com'),
            'name' => 'Maria Santos',
        ])
        ->and($order['rejection']['at'])->toBeString();

    // The image is kept as evidence, anchored so #25 expires it 7 days on.
    $proof = rtdb()->get("proofs/{$id}");

    expect($proof)->toBeArray()
        ->and($proof['imageBase64'])->toBe('data:image/jpeg;base64,PROOFBYTES')
        ->and($proof['rejectedAt'])->toBeString();
});

it('drops the ticket off the kitchen board the same instant', function (): void {
    $id = seedCashierOrder([
        'status' => OrderStatus::Cooking->value,
        'enteredAmount' => null,
        'verifiedAt' => '2026-10-06T03:55:00Z',
        'verification' => ['verifiedAt' => '2026-10-06T03:55:00Z', 'amount' => 400.0, 'ref' => 'GC999'],
    ]);

    expect(onKitchenBoard())->toContain($id);

    rejectOrder($id)->assertRedirect(route('cashier.queue'));

    // The board mirrors verified, unserved only — a rejected ticket is gone.
    // Reject evicts the board's coalesced entry, so it drops the same instant
    // rather than after the 5 s TTL.
    expect(onKitchenBoard())->not->toContain($id);
});

it('books refundOwed as the proof\'s stored amount when a verified order is spot-check rejected', function (): void {
    // A verified order cleared `enteredAmount` (it is on the proof node), so the
    // claimed figure must come from the proof's `amount`, never the full total.
    $id = seedCashierOrder([
        'status' => OrderStatus::Cooking->value,
        'enteredAmount' => null,
        'verifiedAt' => '2026-10-06T03:55:00Z',
        'verification' => ['verifiedAt' => '2026-10-06T03:55:00Z', 'amount' => 400.0, 'ref' => 'GC999'],
    ]);
    rtdb()->seed("proofs/{$id}", [
        'orderId' => $id,
        'imageBase64' => 'data:image/jpeg;base64,PROOFBYTES',
        'amount' => 350.0,
        'ref' => 'GC999',
    ]);

    rejectOrder($id)->assertRedirect(route('cashier.queue'));

    $order = rtdb()->get("orders/{$id}");

    expect($order['refundOwed'])->toBe(350.0)
        ->and($order['enteredAmount'])->toBe(350.0)
        ->and($order['rejection']['enteredAmount'])->toBe(350.0)
        ->and($order['rejection']['orderTotal'])->toBe(400.0);
});

it('moves a rejected order out of the flagged holds into the rejected list', function (): void {
    $id = seedCashierOrder(['code' => 'HOLD1']);

    $this->get(route('cashier.queue'))->assertOk()->assertSee('Flagged holds')->assertSee('HOLD1');

    rejectOrder($id)->assertRedirect(route('cashier.queue'));

    // The tap evicts the coalesced entry, so the next request recomputes: the
    // row leaves the flagged-holds list and lands in the Rejected list.
    $this->get(route('cashier.queue'))
        ->assertOk()
        ->assertSee('HOLD1')
        ->assertSee('Rejected')
        ->assertSee('Approve');
});

it('treats a duplicate Reject as a friendly no-op', function (): void {
    $id = seedCashierOrder();

    rejectOrder($id)->assertRedirect(route('cashier.queue'));
    $first = rtdb()->get("orders/{$id}");

    $this->travel(3)->seconds();
    rejectOrder($id)->assertRedirect(route('cashier.queue'));
    $second = rtdb()->get("orders/{$id}");

    expect($second['rejectedAt'])->toBe($first['rejectedAt'])
        ->and($second['refundOwed'])->toBe(350.0)
        ->and($second['rejection'])->toBe($first['rejection']);
});

it('treats a stale Reject on an already-served order as a friendly no-op', function (): void {
    $id = seedCashierOrder(['status' => OrderStatus::Served->value]);

    rejectOrder($id)->assertRedirect(route('cashier.queue'));

    $order = rtdb()->get("orders/{$id}");

    expect($order['status'])->toBe(OrderStatus::Served->value)
        ->and($order)->not->toHaveKey('refundOwed')
        ->and($order)->not->toHaveKey('rejection');
});

// --- Tracker --------------------------------------------------------------

it('shows "Payment needs checking" with both numbers on the rejected tracker', function (): void {
    $id = seedCashierOrder();

    rejectOrder($id)->assertRedirect(route('cashier.queue'));

    auth()->logout();
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');

    $this->get(route('orders.state', ['order' => $id]))
        ->assertOk()
        ->assertJson([
            'status' => 'rejected',
            'label' => 'Payment needs checking — see the counter',
            'enteredAmount' => 350.0,
            'total' => 400.0,
        ]);

    $this->get(route('orders.show', ['order' => $id]))
        ->assertOk()
        ->assertSee('Payment needs checking — see the counter')
        ->assertSee('350.00')
        ->assertSee('400.00')
        // A rejected order attaches no further proof.
        ->assertDontSee('Attach payment screenshot');
});

// --- Approve (the undo) ---------------------------------------------------

it('rejoins the ticket by age on Approve without restarting the timer', function (): void {
    $id = seedCashierOrder([
        'status' => OrderStatus::Cooking->value,
        'enteredAmount' => null,
        'verifiedAt' => '2026-10-06T03:55:00Z',
        'verification' => ['verifiedAt' => '2026-10-06T03:55:00Z', 'amount' => 400.0, 'ref' => 'GC999'],
    ]);

    rejectOrder($id)->assertRedirect(route('cashier.queue'));
    expect(onKitchenBoard())->not->toContain($id);

    $this->travel(4)->minutes();
    approveOrder($id)->assertRedirect(route('cashier.queue'));

    $order = rtdb()->get("orders/{$id}");

    expect($order['status'])->toBe(OrderStatus::Cooking->value)
        // The original timestamps survive: the timer is not restarted and the
        // NEW badge has no fresh instant to flash on.
        ->and($order['verifiedAt'])->toBe('2026-10-06T03:55:00Z')
        ->and($order['placedAt'])->toBe('2026-10-06T03:50:00Z')
        // The undo clears the obligation and the rejection markers.
        ->and($order['refundOwed'])->toBeNull()
        ->and($order['rejectedAt'])->toBeNull()
        ->and($order['rejection'])->toBeNull();

    // The ticket rejoins the board.
    expect(onKitchenBoard())->toContain($id);
});

it('approves a flagged hold — promotes it to cooking, clears the flags, and stamps verifiedAt', function (): void {
    $id = seedCashierOrder();

    // No ticket on the board while the hold waits on the cashier's tap.
    expect(onKitchenBoard())->not->toContain($id);

    approveOrder($id)->assertRedirect(route('cashier.queue'));

    $order = rtdb()->get("orders/{$id}");

    // A flagged hold is approvable: it promotes to `cooking`, the flag markers
    // are cleared, and the approval instant is the age origin the board reads.
    expect($order['status'])->toBe(OrderStatus::Cooking->value)
        ->and($order['enteredAmount'])->toBeNull()
        ->and($order['flaggedAt'])->toBeNull()
        ->and($order['verifiedAt'])->toBe('2026-10-06T04:00:00Z');

    // The kitchen ticket appears the same instant.
    expect(onKitchenBoard())->toContain($id);
});

it('treats a stale Approve on an order that was never rejected as a no-op', function (): void {
    $id = seedCashierOrder(['status' => OrderStatus::Served->value]);

    approveOrder($id)->assertRedirect(route('cashier.queue'));

    expect(rtdb()->get("orders/{$id}")['status'])->toBe(OrderStatus::Served->value);
});

// --- Void -----------------------------------------------------------------

it('ends the order on Void and never clears refundOwed', function (): void {
    $id = seedCashierOrder();

    rejectOrder($id)->assertRedirect(route('cashier.queue'));
    voidOrder($id)->assertRedirect(route('cashier.queue'));

    $order = rtdb()->get("orders/{$id}");

    expect($order['status'])->toBe(OrderStatus::Voided->value)
        ->and($order['voidedAt'])->toBeString()
        // The refund obligation survives until admin Mark refunded (#32).
        ->and($order['refundOwed'])->toBe(350.0)
        ->and($order['rejection'])->toBeArray();
});

it('treats a stale Void on an already-served order as a friendly no-op', function (): void {
    $id = seedCashierOrder(['status' => OrderStatus::Served->value]);

    voidOrder($id)->assertRedirect(route('cashier.queue'));

    expect(rtdb()->get("orders/{$id}")['status'])->toBe(OrderStatus::Served->value);
});

// --- Queue affordances ----------------------------------------------------

it('offers Approve and Void — never Restore — on a Rejected order', function (): void {
    $id = seedCashierOrder(['code' => 'REJECTED1']);

    rejectOrder($id)->assertRedirect(route('cashier.queue'));

    $this->get(route('cashier.queue'))
        ->assertOk()
        ->assertSee('Rejected')
        ->assertSee('REJECTED1')
        // The claimed refund is shown beside the order total.
        ->assertSee('350.00')
        ->assertSee('400.00')
        ->assertSee('Approve')
        ->assertSee('Void')
        // Restore belongs to Dismissed (#29), never to Rejected.
        ->assertDontSee('Restore');
});

it('offers both Approve and Reject on a flagged hold', function (): void {
    seedCashierOrder(['code' => 'FLAG1']);

    $this->get(route('cashier.queue'))
        ->assertOk()
        ->assertSee('Flagged holds')
        ->assertSee('FLAG1')
        ->assertSee('Approve')
        ->assertSee('Reject');
});

// --- Close sweep invariant ------------------------------------------------

it('leaves a Rejected order untouched by the close sweep', function (): void {
    $id = seedCashierOrder();

    rejectOrder($id)->assertRedirect(route('cashier.queue'));

    $this->artisan('orders:close')->assertSuccessful();

    $order = rtdb()->get("orders/{$id}");

    expect($order['status'])->toBe(OrderStatus::Rejected->value)
        ->and($order['refundOwed'])->toBe(350.0)
        ->and($order['rejection'])->toBeArray()
        // No Unclaimed sale is synthesized for a rejected order.
        ->and(rtdb()->get("sales/{$id}"))->toBeNull();
});

// --- Evidence retention ---------------------------------------------------

it('expires the kept proof seven days after the rejection', function (): void {
    $id = seedCashierOrder();
    rtdb()->seed("proofs/{$id}", [
        'orderId' => $id,
        'imageBase64' => 'data:image/jpeg;base64,PROOFBYTES',
    ]);

    rejectOrder($id)->assertRedirect(route('cashier.queue'));

    // Inside the retention window the image is still there.
    $this->travel(3)->days();
    $this->artisan('proofs:cleanup')->assertSuccessful();
    expect(rtdb()->get("proofs/{$id}"))->toBeArray();

    // Past 7 days it is gone, while the order, record, and refund survive.
    $this->travel(5)->days();
    $this->artisan('proofs:cleanup')->assertSuccessful();

    expect(rtdb()->get("proofs/{$id}"))->toBeNull()
        ->and(rtdb()->get("orders/{$id}")['status'])->toBe(OrderStatus::Rejected->value)
        ->and(rtdb()->get("orders/{$id}")['refundOwed'])->toBe(350.0);
});

// --- Authorization --------------------------------------------------------

it('refuses a customer at the reject, approve, and void routes', function (): void {
    $id = seedCashierOrder();

    auth()->logout();
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');

    rejectOrder($id)->assertForbidden();
    approveOrder($id)->assertForbidden();
    voidOrder($id)->assertForbidden();

    expect(rtdb()->get("orders/{$id}")['status'])->toBe(OrderStatus::Verifying->value);
});
