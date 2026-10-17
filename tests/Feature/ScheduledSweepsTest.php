<?php

declare(strict_types=1);

use App\Orders\CloseWindow;
use App\Orders\Jobs\CloseOrdersJob;
use App\Orders\Jobs\ExpireOrdersJob;
use App\Orders\OrderStatus;
use App\Orders\Payment;
use App\Proofs\Jobs\CleanupProofsJob;
use App\Rtdb\RtdbQuery;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * #25 — scheduled sweeps: expiry, close, proof cleanup.
 *
 * Three sweeps evaluate in Asia/Manila: **Expiry** every minute against
 * `deadlineAt`, dismissing only zero-proof, not-mid-upload orders; **Close**
 * at the weekday closing hour (and on force-close), deciding **Unclaimed** and
 * synthesizing the 1:1 `sales/{id}` row; and **Proof cleanup** daily at
 * end-of-day, deleting a `proofs/{id}` node 7 days after `settledAt` or
 * `rejectedAt` while leaving the order, rejection record, and sale intact.
 */

beforeEach(function (): void {
    seedSettings(['hours' => weekdayHours()]);
});

/**
 * Arrange an online order at `orders/{id}`, exactly as #23's place-order action
 * writes it — `deadlineAt = placedAt + 15 min`.
 *
 * @param  array<string, mixed>  $overrides
 */
function seedSweepOrder(array $overrides = []): string
{
    $id = (string) Str::ulid();

    rtdb()->seed("orders/{$id}", [
        'code' => 'SWP1',
        'type' => 'online',
        'status' => OrderStatus::AwaitingPayment->value,
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

/**
 * Arrange a served order (the terminal state a proof expires against).
 *
 * @param  array<string, mixed>  $overrides
 */
function seedServedOrder(array $overrides = []): string
{
    $id = (string) Str::ulid();

    rtdb()->seed("orders/{$id}", [
        'code' => 'SWP2',
        'type' => 'online',
        'status' => OrderStatus::Served->value,
        'lines' => [],
        'total' => 200.0,
        'payments' => [['method' => 'gcash', 'amount' => 200.0]],
        'change' => 0.0,
        'customerUid' => 'sub-ada',
        'placedAt' => '2026-09-20T04:00:00Z',
        'pickupAt' => null,
        'deadlineAt' => '2026-09-20T04:15:00Z',
        'settledAt' => '2026-09-28T04:00:00Z',
        ...$overrides,
    ]);

    return $id;
}

/**
 * Arrange a proof node at `proofs/{id}`.
 *
 * @param  array<string, mixed>  $overrides
 */
function seedProofNode(string $orderId, array $overrides = []): string
{
    rtdb()->seed("proofs/{$orderId}", [
        'orderId' => $orderId,
        'imageBase64' => 'data:image/jpeg;base64,PROOF',
        'amount' => 200.0,
        'ref' => 'GC1234567890',
        'uploadedAt' => '2026-09-20T04:05:00Z',
        ...$overrides,
    ]);

    return $orderId;
}

// --- Expiry ---------------------------------------------------------------

it('dismisses a zero-proof order past its payment deadline', function (): void {
    $this->travelTo(new DateTimeImmutable('2026-10-06 12:20:00', new DateTimeZone('Asia/Manila')));
    $id = seedSweepOrder();

    $this->artisan('orders:expire')->assertSuccessful();

    $order = rtdb()->get("orders/{$id}");

    expect($order['status'])->toBe(OrderStatus::Dismissed->value)
        ->and($order['dismissedAt'])->toBeString();
});

it('holds an order whose proof upload is mid-flight past the deadline', function (): void {
    $this->travelTo(new DateTimeImmutable('2026-10-06 12:20:00', new DateTimeZone('Asia/Manila')));
    $id = seedSweepOrder([
        'proofInFlight' => true,
        'uploadStartedAt' => '2026-10-06T04:14:00Z',
    ]);

    $this->artisan('orders:expire')->assertSuccessful();

    expect(rtdb()->get("orders/{$id}")['status'])->toBe(OrderStatus::AwaitingPayment->value);
});

it('holds an order whose proof already arrived past the deadline', function (): void {
    $this->travelTo(new DateTimeImmutable('2026-10-06 12:20:00', new DateTimeZone('Asia/Manila')));
    $id = seedSweepOrder([
        'status' => OrderStatus::Verifying->value,
        'proofInFlight' => false,
        'proofReceivedAt' => '2026-10-06T04:14:00Z',
        'enteredAmount' => 150.0,
        'gcashRef' => 'GC999',
    ]);

    $this->artisan('orders:expire')->assertSuccessful();

    expect(rtdb()->get("orders/{$id}")['status'])->toBe(OrderStatus::Verifying->value);
});

it('leaves an order still inside its payment window untouched', function (): void {
    $this->travelTo(new DateTimeImmutable('2026-10-06 12:10:00', new DateTimeZone('Asia/Manila')));
    $id = seedSweepOrder();

    $this->artisan('orders:expire')->assertSuccessful();

    expect(rtdb()->get("orders/{$id}")['status'])->toBe(OrderStatus::AwaitingPayment->value);
});

it('does not dismiss a verified order past its deadline', function (): void {
    $this->travelTo(new DateTimeImmutable('2026-10-06 12:20:00', new DateTimeZone('Asia/Manila')));
    $id = seedSweepOrder(['status' => OrderStatus::Cooking->value]);

    $this->artisan('orders:expire')->assertSuccessful();

    expect(rtdb()->get("orders/{$id}")['status'])->toBe(OrderStatus::Cooking->value);
});

it('clears the cashier queue by dropping the dismissed order out of awaiting payment', function (): void {
    $this->travelTo(new DateTimeImmutable('2026-10-06 12:20:00', new DateTimeZone('Asia/Manila')));
    $id = seedSweepOrder();

    $this->artisan('orders:expire')->assertSuccessful();

    // The cashier queue reads the payment states; the dismissed order is no
    // longer among them, so the queue read clears it with no extra write.
    $awaiting = rtdb()->query(
        'orders',
        RtdbQuery::orderBy('status')->equalTo(OrderStatus::AwaitingPayment->value),
    );

    expect($awaiting)->not->toHaveKey($id);
});

// --- Close ----------------------------------------------------------------

it('decides Unclaimed for a paid, cooked order never collected', function (): void {
    $id = seedSweepOrder(['status' => OrderStatus::Cooking->value]);

    $this->artisan('orders:close')->assertSuccessful();

    $order = rtdb()->get("orders/{$id}");

    expect($order['status'])->toBe(OrderStatus::Unclaimed->value)
        ->and($order['unclaimedAt'])->toBeString();
});

it('synthesizes the Unclaimed sale once and is idempotent across a second close', function (): void {
    $this->travelTo(new DateTimeImmutable('2026-10-06 21:00:00', new DateTimeZone('Asia/Manila')));
    $id = seedSweepOrder(['status' => OrderStatus::Cooking->value]);

    $this->artisan('orders:close')->assertSuccessful();

    $sale = rtdb()->get("sales/{$id}");

    expect($sale)->toBeArray()
        ->and($sale['code'])->toBe('SWP1')
        ->and($sale['total'])->toBe(200.0)
        ->and($sale['soldAt'])->toBeString();

    $soldAt = $sale['soldAt'];

    // A second close (the force-close path re-running) finds the 1:1 key and
    // writes nothing — no double sale and no changed soldAt.
    $this->travel(30)->minutes();

    $this->artisan('orders:close')->assertSuccessful();

    expect(rtdb()->get("sales/{$id}")['soldAt'])->toBe($soldAt)
        ->and(rtdb()->get("orders/{$id}")['status'])->toBe(OrderStatus::Unclaimed->value);
});

it('does not overwrite the sale a settled walk-in already wrote', function (): void {
    $id = seedSweepOrder([
        'code' => 'WALK1',
        'type' => 'walkin',
        'status' => OrderStatus::Cooking->value,
        'settledAt' => '2026-10-06T04:05:00Z',
        'handledBy' => ['uid' => 'staff-1', 'name' => 'Maria'],
    ]);

    rtdb()->seed("sales/{$id}", [
        'code' => 'WALK1',
        'type' => 'walkin',
        'total' => 200.0,
        'payments' => [['method' => 'cash', 'amount' => 200.0]],
        'soldAt' => '2026-10-06T04:05:00Z',
        'handledBy' => ['uid' => 'staff-1', 'name' => 'Maria'],
    ]);

    $this->artisan('orders:close')->assertSuccessful();

    $sale = rtdb()->get("sales/{$id}");

    expect($sale['soldAt'])->toBe('2026-10-06T04:05:00Z')
        ->and($sale['handledBy'])->toBe(['uid' => 'staff-1', 'name' => 'Maria'])
        ->and(rtdb()->get("orders/{$id}")['status'])->toBe(OrderStatus::Unclaimed->value);
});

it('leaves a Rejected order alone — that is #27\'s invariant', function (): void {
    $id = seedSweepOrder([
        'status' => OrderStatus::Rejected->value,
        'refundOwed' => 150.0,
        'rejection' => ['enteredAmount' => 150.0, 'total' => 200.0, 'ref' => 'GC999'],
    ]);

    $this->artisan('orders:close')->assertSuccessful();

    $order = rtdb()->get("orders/{$id}");

    expect($order['status'])->toBe(OrderStatus::Rejected->value)
        ->and($order['refundOwed'])->toBe(150.0)
        ->and($order['rejection'])->toBeArray()
        ->and(rtdb()->get("sales/{$id}"))->toBeNull();
});

it('leaves Served and Dismissed orders alone', function (): void {
    $served = seedServedOrder();
    $dismissed = seedSweepOrder(['status' => OrderStatus::Dismissed->value]);

    $this->artisan('orders:close')->assertSuccessful();

    expect(rtdb()->get("orders/{$served}")['status'])->toBe(OrderStatus::Served->value)
        ->and(rtdb()->get("orders/{$dismissed}")['status'])->toBe(OrderStatus::Dismissed->value)
        ->and(rtdb()->get("sales/{$dismissed}"))->toBeNull();
});

it('clears the server-owned kitchen board when an order is closed out', function (): void {
    // Freeze inside the ticket's 15-minute board window: the board's read
    // applies the same expiry production does, so a real wall-clock run would
    // have dropped this Oct-6 order before the assertion.
    $this->travelTo(new DateTimeImmutable('2026-10-06 12:00:00', new DateTimeZone('Asia/Manila')));
    $id = seedSweepOrder(['status' => OrderStatus::Cooking->value]);

    expect(onKitchenBoard())->toContain($id);

    $this->artisan('orders:close')->assertSuccessful();

    expect(onKitchenBoard())->not->toContain($id);
});

it('stamps settledAt on an Unclaimed order so its proof expires on schedule', function (): void {
    $this->travelTo(new DateTimeImmutable('2026-10-06 21:00:00', new DateTimeZone('Asia/Manila')));
    $id = seedSweepOrder(['status' => OrderStatus::Cooking->value]);
    seedProofNode($id);

    $this->artisan('orders:close')->assertSuccessful();

    // The close is the terminal instant an Unclaimed order never reaches
    // through Mark served; the cleanup sweep keys off it.
    expect(rtdb()->get("orders/{$id}")['settledAt'])->toBeString();

    $this->travelTo(new DateTimeImmutable('2026-10-13 23:59:00', new DateTimeZone('Asia/Manila')));
    $this->artisan('proofs:cleanup')->assertSuccessful();

    expect(rtdb()->get("proofs/{$id}"))->toBeNull();
});

it('keeps a walk-in\'s counter settledAt when the close decides it Unclaimed', function (): void {
    $this->travelTo(new DateTimeImmutable('2026-10-06 21:00:00', new DateTimeZone('Asia/Manila')));
    $id = seedSweepOrder([
        'code' => 'WALK9',
        'type' => 'walkin',
        'status' => OrderStatus::Cooking->value,
        'settledAt' => '2026-10-06T04:05:00Z',
        'handledBy' => ['uid' => 'staff-1', 'name' => 'Maria'],
    ]);

    $this->artisan('orders:close')->assertSuccessful();

    // The walk-in settled at the counter (the receipt prints this instant), so
    // the close must not overwrite it with its own time.
    expect(rtdb()->get("orders/{$id}")['settledAt'])->toBe('2026-10-06T04:05:00Z');
});

// --- Proof cleanup --------------------------------------------------------

it('deletes a proof seven days after a terminal order settled', function (): void {
    $this->travelTo(new DateTimeImmutable('2026-10-06 23:59:00', new DateTimeZone('Asia/Manila')));
    $id = seedServedOrder(['settledAt' => '2026-09-28T04:00:00Z']);
    seedProofNode($id);

    $this->artisan('proofs:cleanup')->assertSuccessful();

    expect(rtdb()->get("proofs/{$id}"))->toBeNull();
});

it('keeps a proof younger than seven days', function (): void {
    $this->travelTo(new DateTimeImmutable('2026-10-06 23:59:00', new DateTimeZone('Asia/Manila')));
    $id = seedServedOrder(['settledAt' => '2026-10-01T04:00:00Z']);
    seedProofNode($id);

    $this->artisan('proofs:cleanup')->assertSuccessful();

    expect(rtdb()->get("proofs/{$id}"))->toBeArray();
});

it('deletes a rejected proof seven days after it was rejected', function (): void {
    $this->travelTo(new DateTimeImmutable('2026-10-06 23:59:00', new DateTimeZone('Asia/Manila')));
    $id = seedSweepOrder(['status' => OrderStatus::Rejected->value]);
    seedProofNode($id, ['rejectedAt' => '2026-09-28T04:00:00Z']);

    $this->artisan('proofs:cleanup')->assertSuccessful();

    expect(rtdb()->get("proofs/{$id}"))->toBeNull();
});

it('leaves the order, rejection record, and sale intact when a proof expires', function (): void {
    $this->travelTo(new DateTimeImmutable('2026-10-06 23:59:00', new DateTimeZone('Asia/Manila')));
    $id = seedServedOrder(['settledAt' => '2026-09-28T04:00:00Z']);
    seedProofNode($id);
    rtdb()->seed("sales/{$id}", [
        'code' => 'SWP2',
        'type' => 'online',
        'total' => 200.0,
        'payments' => [['method' => 'gcash', 'amount' => 200.0]],
        'soldAt' => '2026-09-28T04:00:00Z',
    ]);

    $this->artisan('proofs:cleanup')->assertSuccessful();

    expect(rtdb()->get("proofs/{$id}"))->toBeNull()
        ->and(rtdb()->get("orders/{$id}")['status'])->toBe(OrderStatus::Served->value)
        ->and(rtdb()->get("sales/{$id}")['total'])->toBe(200.0);
});

it('deletes a rental proof seven days after the booking reached a terminal state', function (): void {
    $this->travelTo(new DateTimeImmutable('2026-10-06 23:59:00', new DateTimeZone('Asia/Manila')));

    // A returned booking whose proof lives at proofs/rental-{id}. The booking
    // carries no settledAt/rejectedAt — only its terminalAt anchors the clock.
    seedRentalBooking('r1', [
        'status' => 'returned',
        'returnedAt' => '2026-09-28T04:00:00Z',
        'terminalAt' => '2026-09-28T04:00:00Z',
    ]);
    rtdb()->seed('proofs/rental-r1', [
        'bookingId' => 'r1',
        'imageBase64' => 'data:image/jpeg;base64,PROOF',
        'amount' => 150.0,
        'ref' => 'GC1234567890',
    ]);

    $this->artisan('proofs:cleanup')->assertSuccessful();

    expect(rtdb()->get('proofs/rental-r1'))->toBeNull()
        // The booking record is left intact.
        ->and(rtdb()->get('rentalBookings/r1')['status'])->toBe('returned');
});

it('keeps a rental proof younger than seven days', function (): void {
    $this->travelTo(new DateTimeImmutable('2026-10-06 23:59:00', new DateTimeZone('Asia/Manila')));

    seedRentalBooking('r1', [
        'status' => 'lapsed',
        'lapsedAt' => '2026-10-01T04:00:00Z',
        'terminalAt' => '2026-10-01T04:00:00Z',
    ]);
    rtdb()->seed('proofs/rental-r1', [
        'bookingId' => 'r1',
        'imageBase64' => 'data:image/jpeg;base64,PROOF',
    ]);

    $this->artisan('proofs:cleanup')->assertSuccessful();

    expect(rtdb()->get('proofs/rental-r1'))->toBeArray();
});

it('expires a rental proof seven days after a cancelled booking', function (): void {
    $this->travelTo(new DateTimeImmutable('2026-10-06 23:59:00', new DateTimeZone('Asia/Manila')));

    seedRentalBooking('r1', [
        'status' => 'cancelled',
        'cancelledAt' => '2026-09-28T04:00:00Z',
        'terminalAt' => '2026-09-28T04:00:00Z',
    ]);
    rtdb()->seed('proofs/rental-r1', [
        'bookingId' => 'r1',
        'imageBase64' => 'data:image/jpeg;base64,PROOF',
    ]);

    $this->artisan('proofs:cleanup')->assertSuccessful();

    expect(rtdb()->get('proofs/rental-r1'))->toBeNull()
        ->and(rtdb()->get('rentalBookings/r1')['status'])->toBe('cancelled');
});

it('prunes stale checkout idempotency records, keeping fresh ones', function (): void {
    $this->travelTo(new DateTimeImmutable('2026-10-06 23:59:00', new DateTimeZone('Asia/Manila')));

    // A record that crossed the two-day retention on the previous daily run,
    // and one still inside it — the `idem` tree must never grow without bound.
    rtdb()->seed('idem/old-key', ['orderId' => 'o1', 'updatedAt' => '2026-10-04T04:00:00Z']);
    rtdb()->seed('idem/new-key', ['orderId' => 'o2', 'updatedAt' => '2026-10-06T04:00:00Z']);

    $this->artisan('proofs:cleanup')->assertSuccessful();

    expect(rtdb()->get('idem/old-key'))->toBeNull()
        ->and(rtdb()->get('idem/new-key'))->toBeArray();
});

// --- Scheduler & timezone -------------------------------------------------

it('runs the three sweeps on the Laravel scheduler in Asia/Manila', function (): void {
    // Booting the console kernel loads routes/console.php, where the schedule
    // lives; `schedule:list` is the same surface `schedule:run` reads.
    $this->artisan('schedule:list')->assertSuccessful();

    $events = collect(app(Schedule::class)->events())
        ->keyBy(fn($event): string => (string) $event->description);

    expect($events->has('orders:expire'))->toBeTrue()
        ->and($events->has('orders:close'))->toBeTrue()
        ->and($events->has('proofs:cleanup'))->toBeTrue();

    $expire = $events->get('orders:expire');
    $close = $events->get('orders:close');
    $cleanup = $events->get('proofs:cleanup');

    expect($expire->expression)->toBe('* * * * *')
        ->and($expire->timezone)->toBe('Asia/Manila')
        ->and($close->expression)->toBe('* * * * *')
        ->and($close->timezone)->toBe('Asia/Manila')
        ->and($cleanup->expression)->toBe('59 23 * * *')
        ->and($cleanup->timezone)->toBe('Asia/Manila');
});

it('closes at the weekday closing hour in Asia/Manila', function (): void {
    $window = app(CloseWindow::class);

    expect($window->isDueAt(Carbon::parse('2026-10-06 21:00:00', 'Asia/Manila')))->toBeTrue()
        ->and($window->isDueAt(Carbon::parse('2026-10-06 20:59:00', 'Asia/Manila')))->toBeFalse()
        ->and($window->isDueAt(Carbon::parse('2026-10-06 21:01:00', 'Asia/Manila')))->toBeFalse();
});

it('closes whenever the force-close toggle is on', function (): void {
    seedSettings(['hours' => weekdayHours(), 'forceClose' => true]);

    expect(app(CloseWindow::class)->isDueAt(Carbon::parse('2026-10-06 12:00:00', 'Asia/Manila')))
        ->toBeTrue();
});

it('dispatches each sweep as a unique job so a double-run cannot double-write', function (): void {
    expect(new ExpireOrdersJob())->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldBeUnique::class)
        ->and(new CloseOrdersJob())->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldBeUnique::class)
        ->and(new CleanupProofsJob())->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldBeUnique::class);
});

// --- Manual fallback ------------------------------------------------------

it('runs each sweep by hand as the documented manual fallback', function (): void {
    $this->travelTo(new DateTimeImmutable('2026-10-06 21:00:00', new DateTimeZone('Asia/Manila')));

    $id = seedSweepOrder(['status' => OrderStatus::Cooking->value]);
    seedProofNode($id, ['rejectedAt' => '2026-09-28T04:00:00Z']);

    // The fallback commands run the same sweep synchronously, so an operator can
    // recover with the scheduler down.
    $this->artisan('orders:expire')->assertSuccessful();
    $this->artisan('orders:close')->assertSuccessful();
    $this->artisan('proofs:cleanup')->assertSuccessful();

    expect(rtdb()->get("orders/{$id}")['status'])->toBe(OrderStatus::Unclaimed->value)
        ->and(rtdb()->get("proofs/{$id}"))->toBeNull();
});

it('widens the expiry lookback to recover lapses older than the scheduled window', function (): void {
    $this->travelTo(new DateTimeImmutable('2026-10-06 12:20:00', new DateTimeZone('Asia/Manila')));

    // A deadline three days in the past — older than the default one-day window.
    $id = seedSweepOrder(['deadlineAt' => '2026-10-03T04:15:00Z']);

    $this->artisan('orders:expire')->assertSuccessful();
    expect(rtdb()->get("orders/{$id}")['status'])->toBe(OrderStatus::AwaitingPayment->value);

    $this->artisan('orders:expire', ['--lookback-hours' => '168'])->assertSuccessful();
    expect(rtdb()->get("orders/{$id}")['status'])->toBe(OrderStatus::Dismissed->value);
});

it('widens the proof cleanup window to recover an outage', function (): void {
    $this->travelTo(new DateTimeImmutable('2026-10-06 23:59:00', new DateTimeZone('Asia/Manila')));

    // Settled ten days ago — older than the default two-day window.
    $id = seedServedOrder(['settledAt' => '2026-09-25T04:00:00Z']);
    seedProofNode($id);

    $this->artisan('proofs:cleanup')->assertSuccessful();
    expect(rtdb()->get("proofs/{$id}"))->toBeArray();

    $this->artisan('proofs:cleanup', ['--window-days' => '30'])->assertSuccessful();
    expect(rtdb()->get("proofs/{$id}"))->toBeNull();
});
