<?php

declare(strict_types=1);

use App\Orders\OrderStatus;
use App\Orders\OrderTracker;
use App\Staff\StaffDirectory;
use App\Staff\StaffRole;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * #26 — the cashier online pickup queue and Mark served.
 *
 * Prepaid orders arrive ready-to-pay with no approval tap; verified orders land
 * in the recently-verified list for at-leisure spot-check with Reject reachable
 * until Mark served; in-window orders with no screenshot show as awaiting proof.
 * Mark served is the one happy-path tap: it flips the tracker to READY and
 * writes the sale to `sales/{id}` with `handledBy`. Every tap is status-guarded,
 * so a duplicate or stale tap is a friendly no-op. The queue read is coalesced
 * behind a cache entry and never carries the sibling proof image bytes.
 */

beforeEach(function (): void {
    seedStaff('maria@example.com', StaffRole::Cashier, 'Maria Santos');
    signIn('maria@example.com', 'Maria Santos');

    // 2026-10-06 12:00 Asia/Manila is 04:00 UTC — the order deadlines below
    // straddle this instant.
    $this->travelTo(new DateTimeImmutable('2026-10-06 12:00:00', new DateTimeZone('Asia/Manila')));
});

/**
 * Arrange an online pickup order exactly as #23/#24 write it.
 *
 * @param  array<string, mixed>  $overrides
 */
function seedOnlineOrder(array $overrides = []): string
{
    $id = (string) Str::ulid();

    rtdb()->seed("orders/{$id}", [
        'code' => 'ONL1',
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

function serveOrder(string $id): TestResponse
{
    return test()->post(route('cashier.orders.serve', ['order' => $id]));
}

it('shows an in-window order with no screenshot as awaiting proof', function (): void {
    seedOnlineOrder(['code' => 'WAIT1']);

    $this->get(route('cashier.queue'))
        ->assertOk()
        ->assertSee('Awaiting proof')
        ->assertSee('WAIT1');
});

it('leaves an order out of awaiting proof once its payment window has lapsed', function (): void {
    seedOnlineOrder(['code' => 'LAPSED', 'deadlineAt' => '2026-10-06T03:00:00Z']);

    $this->get(route('cashier.queue'))->assertOk()->assertDontSee('LAPSED');
});

it('leaves an order with a screenshot out of awaiting proof', function (): void {
    seedOnlineOrder(['code' => 'HASPROOF', 'proofReceivedAt' => '2026-10-06T04:02:00Z']);

    $this->get(route('cashier.queue'))->assertOk()->assertDontSee('HASPROOF');
});

it('shows a verified order ready-to-pay with no approval tap', function (): void {
    seedOnlineOrder([
        'code' => 'RDY1',
        'status' => OrderStatus::Cooking->value,
        'proofReceivedAt' => '2026-10-06T04:05:00Z',
    ]);

    $this->get(route('cashier.queue'))
        ->assertOk()
        ->assertSee('Ready to pay')
        ->assertSee('RDY1')
        // Auto-verified: the order arrives without the cashier approving it.
        ->assertDontSee('Approve')
        // The one happy-path tap.
        ->assertSee('Mark served');
});

it('lists a verified order for spot-check with Reject reachable until served', function (): void {
    $id = seedOnlineOrder([
        'code' => 'VER1',
        'status' => OrderStatus::Cooking->value,
        'proofReceivedAt' => '2026-10-06T04:05:00Z',
    ]);

    $this->get(route('cashier.queue'))
        ->assertOk()
        ->assertSee('Recently verified')
        ->assertSee('VER1')
        ->assertSee('Reject');

    // Mark served is the only way off the spot-check list.
    serveOrder($id)->assertRedirect(route('cashier.queue'));

    $this->get(route('cashier.queue'))->assertOk()->assertDontSee('VER1');
});

it('marks an order served: the tracker reads READY and a sale row carries handledBy', function (): void {
    $id = seedOnlineOrder([
        'code' => 'SERVE1',
        'status' => OrderStatus::Cooking->value,
        'proofReceivedAt' => '2026-10-06T04:05:00Z',
    ]);

    serveOrder($id)->assertRedirect(route('cashier.queue'));

    $order = rtdb()->get("orders/{$id}");

    expect($order['status'])->toBe(OrderStatus::Served->value)
        ->and($order['servedAt'])->toBeString();

    // The customer's tracker flips to READY the same instant.
    expect(app(OrderTracker::class)->state($id)?->label)->toBe('READY');

    $expected = ['uid' => StaffDirectory::idForEmail('maria@example.com'), 'name' => 'Maria Santos'];
    $sale = rtdb()->get("sales/{$id}");

    expect($sale)->toBeArray()
        ->and($sale['code'])->toBe('SERVE1')
        ->and($sale['total'])->toBe(200.0)
        ->and($sale['handledBy'])->toBe($expected);
});

it('treats a duplicate Mark served tap as a friendly no-op', function (): void {
    $id = seedOnlineOrder([
        'code' => 'DUP1',
        'status' => OrderStatus::Cooking->value,
        'proofReceivedAt' => '2026-10-06T04:05:00Z',
    ]);

    serveOrder($id)->assertRedirect(route('cashier.queue'));
    $first = rtdb()->get("orders/{$id}");

    // A second tap a few seconds later must not re-stamp the served instant.
    $this->travel(3)->seconds();
    serveOrder($id)->assertRedirect(route('cashier.queue'));
    $second = rtdb()->get("orders/{$id}");

    expect($second['servedAt'])->toBe($first['servedAt'])
        ->and($second['status'])->toBe(OrderStatus::Served->value);
});

it('treats a stale Mark served tap on an unpaid order as a friendly no-op', function (): void {
    $id = seedOnlineOrder(['code' => 'STALE1']);

    serveOrder($id)->assertRedirect(route('cashier.queue'));

    expect(rtdb()->get("orders/{$id}")['status'])->toBe(OrderStatus::AwaitingPayment->value)
        ->and(rtdb()->get("sales/{$id}"))->toBeNull();
});

it('never carries proof image bytes in the queue read', function (): void {
    $id = seedOnlineOrder([
        'code' => 'IMG1',
        'status' => OrderStatus::Cooking->value,
        'proofReceivedAt' => '2026-10-06T04:05:00Z',
    ]);

    rtdb()->seed("proofs/{$id}", [
        'orderId' => $id,
        'imageBase64' => 'data:image/jpeg;base64,PROOFBYTES',
        'amount' => 200.0,
    ]);

    $html = $this->get(route('cashier.queue'))->assertOk()->getContent();
    $json = json_encode($this->get(route('cashier.queue.state'))->assertOk()->json());

    expect($html)->toBeString()
        ->and($html)->not->toContain('imageBase64')
        ->and($html)->not->toContain('PROOFBYTES')
        ->and($json)->toBeString()
        ->and($json)->not->toContain('imageBase64')
        ->and($json)->not->toContain('PROOFBYTES');
});

it('serves the queue from the coalesced cache entry', function (): void {
    seedOnlineOrder([
        'code' => 'CACHE1',
        'status' => OrderStatus::Cooking->value,
        'proofReceivedAt' => '2026-10-06T04:05:00Z',
    ]);

    $this->get(route('cashier.queue'))->assertOk()->assertSee('CACHE1');

    // A row written straight to RTDB without evicting the entry is not seen
    // until the poll cadence lapses — the read is coalesced behind the cache.
    seedOnlineOrder([
        'code' => 'CACHE2',
        'status' => OrderStatus::Cooking->value,
        'proofReceivedAt' => '2026-10-06T04:05:00Z',
    ]);

    $this->get(route('cashier.queue'))->assertOk()->assertSee('CACHE1')->assertDontSee('CACHE2');

    $this->travel(6)->seconds();

    $this->get(route('cashier.queue'))->assertOk()->assertSee('CACHE2');
});

it('drops a served order from the queue on the cashier\'s own tap', function (): void {
    $id = seedOnlineOrder([
        'code' => 'EVICT1',
        'status' => OrderStatus::Cooking->value,
        'proofReceivedAt' => '2026-10-06T04:05:00Z',
    ]);

    $this->get(route('cashier.queue'))->assertOk()->assertSee('EVICT1');

    serveOrder($id)->assertRedirect(route('cashier.queue'));

    // No waiting out the TTL: the tap evicts the entry.
    $this->get(route('cashier.queue'))->assertOk()->assertDontSee('EVICT1');
});

it('names the cashier on duty on the queue screen', function (): void {
    $this->get(route('cashier.queue'))
        ->assertOk()
        ->assertSee('Cashier on duty')
        ->assertSee('Maria Santos');
});

it('refuses a customer at the queue', function (): void {
    auth()->logout();
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');

    $this->get(route('cashier.queue'))->assertForbidden();
    $this->post(route('cashier.orders.serve', ['order' => 'nope']))->assertForbidden();
});
