<?php

declare(strict_types=1);

use App\Orders\OrderTracker;
use Illuminate\Support\Facades\Cache;

/**
 * #23 — cart → place order → GCash gate → order tracker.
 *
 * The customer builds a cart (inline steppers, add-on picks, computed totals),
 * picks a pickup time (default ASAP, earliest now + 15 min, too-soon refused),
 * and Places the order. The POST is sign-in-gated, validated and throttled,
 * carries a client-generated idempotency key (a replay returns the original
 * order code and opens no second window), returns the order code, and auto-sends
 * the GCash number + official QR inside a branded frame, starting the 15-minute
 * payment window. The tracker is a live status screen polling every 5–10 s,
 * bound to the session and `users/{uid}`, showing the order code never typed.
 */

beforeEach(function (): void {
    seedCategory('drinks', 'Drinks');
    seedProduct('p1', ['name' => 'Milktea', 'price' => 200.0]);
    seedSettings([
        'hours' => weekdayHours(),
        'gcash' => ['number' => '0917 123 4567', 'qr' => 'data:image/jpeg;base64,QRDATA'],
    ]);

    $this->travelTo(new DateTimeImmutable('2026-10-06 12:00:00', new DateTimeZone('Asia/Manila')));
});

/**
 * Put a product in the signed-in customer's cart through the cart POST.
 *
 * @param  array<int, string>  $addOns
 */
function addToCart(string $productId, int $quantity = 1, array $addOns = []): void
{
    test()->post(route('cart.update'), [
        'product' => $productId,
        'quantity' => $quantity,
        'addOns' => $addOns,
    ])->assertRedirect();
}

/**
 * Place the order through the checkout POST and return the redirect response.
 */
function placeOrder(string $key = 'attempt-1', ?string $pickup = null): \Illuminate\Testing\TestResponse
{
    return test()->post(route('checkout.store'), [
        'idempotencyKey' => $key,
        'pickup' => $pickup,
    ]);
}

it('adds a product to the cart from the menu and product pages', function (): void {
    seedCategory('drinks', 'Drinks');
    seedProduct('p1', ['name' => 'Milktea', 'price' => 200.0]);
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');

    $this->post(route('cart.update'), ['product' => 'p1', 'quantity' => 1])->assertRedirect();

    // The cart is visible from the header and the menu links to it.
    $this->get(route('cart.show'))->assertOk()->assertSee('Milktea');
});

it('prompts the Google button when a signed-out visitor taps add to cart', function (): void {
    seedCategory('drinks', 'Drinks');
    seedProduct('p1', ['name' => 'Milktea', 'price' => 200.0]);

    $this->post(route('cart.update'), ['product' => 'p1', 'quantity' => 1])
        ->assertRedirect(route('login'));
});

it('pre-checks a cart line\'s saved add-on selection from the account', function (): void {
    seedProduct('p2', [
        'name' => 'Milktea',
        'price' => 200.0,
        'addOns' => [
            ['name' => 'Pearl', 'price' => 15.0],
            ['name' => 'Less ice', 'price' => 0.0],
        ],
    ]);

    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');

    // #22 saves the last add-on pick on the product page.
    $this->post(route('prefs.add-ons', ['product' => 'p2']), ['addOns' => ['Pearl']])->assertRedirect();

    addToCart('p2', 1, []);

    $this->get(route('cart.show'))
        ->assertOk()
        ->assertSee('value="Pearl" checked', false)
        ->assertDontSee('value="Less ice" checked', false);
});

it('shows the cart with computed totals from the steppers and add-ons', function (): void {
    seedProduct('p2', [
        'name' => 'Milktea',
        'price' => 200.0,
        'addOns' => [
            ['name' => 'Pearl', 'price' => 15.0],
            ['name' => 'Less ice', 'price' => 0.0],
        ],
    ]);

    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');

    addToCart('p2', 2, ['Pearl']);

    $this->get(route('cart.show'))
        ->assertOk()
        ->assertSee('Milktea')
        ->assertSee('Pearl')
        ->assertSee('₱430.00');
});

it('requires sign-in to open the cart and checkout', function (): void {
    $this->get(route('cart.show'))->assertRedirect(route('login'));
    $this->get(route('checkout.show'))->assertRedirect(route('login'));
});

it('recomputes the cart total when the stepper quantity changes', function (): void {
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');

    addToCart('p1', 1);
    $this->get(route('cart.show'))->assertOk()->assertSee('₱200.00');

    addToCart('p1', 3);
    $this->get(route('cart.show'))->assertOk()->assertSee('₱600.00');
});

it('defaults the pickup time to ASAP and floors the picker at now + 15 minutes', function (): void {
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    addToCart('p1');

    $this->get(route('checkout.show'))
        ->assertOk()
        ->assertSee('ASAP')
        // 12:00 Manila + 15 min = 12:15, rendered as the picker's min.
        ->assertSee('2026-10-06T12:15', false)
        ->assertSee('name="pickup"', false);
});

it('requires sign-in to place an order', function (): void {
    placeOrder()->assertRedirect(route('login'));
});

it('refuses a pickup time earlier than now + 15 minutes', function (): void {
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    addToCart('p1');

    placeOrder(pickup: '2026-10-06T12:14')
        ->assertSessionHasErrors('pickup');

    expect(rtdb()->get('orders'))->toBeNull();
});

it('refuses an empty cart', function (): void {
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');

    placeOrder()->assertSessionHasErrors('cart');

    expect(rtdb()->get('orders'))->toBeNull();
});

it('throttles place order at 10 per minute per signed-in user', function (): void {
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');

    // No cart: each attempt is refused by validation but still counted.
    for ($i = 0; $i < 10; $i++) {
        placeOrder("attempt-{$i}")->assertStatus(302);
    }

    placeOrder('attempt-11')->assertStatus(429);
});

it('recovers the checkout limit inside its one-minute window', function (): void {
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');

    // No cart: each attempt is refused by validation but still counted.
    for ($i = 0; $i < 10; $i++) {
        placeOrder("recover-{$i}")->assertStatus(302);
    }

    placeOrder('recover-over')->assertStatus(429);

    // The limit decays inside its window: once the minute elapses the same
    // customer is served again, so a burst never locks the counter out.
    $this->travel(61)->seconds();

    placeOrder('recover-after')->assertStatus(302);
});

it('counts anonymous checkout traffic per real forwarded IP', function (): void {
    // Behind one proxy, two customers must land in separate buckets.
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1']);

    for ($i = 0; $i < 30; $i++) {
        $this->withHeaders(['X-Forwarded-For' => '203.0.113.10'])
            ->post(route('checkout.store'), ['idempotencyKey' => "a-{$i}"])
            ->assertStatus(302);
    }

    $this->withHeaders(['X-Forwarded-For' => '203.0.113.10'])
        ->post(route('checkout.store'), ['idempotencyKey' => 'a-over'])
        ->assertStatus(429);

    // A second real IP behind the same proxy is unaffected.
    $this->withHeaders(['X-Forwarded-For' => '203.0.113.11'])
        ->post(route('checkout.store'), ['idempotencyKey' => 'b-1'])
        ->assertStatus(302);
});

it('returns the order code and opens the GCash frame with a 15-minute window', function (): void {
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    addToCart('p1', 2);

    placeOrder()->assertRedirect(route('orders.tracker'));

    $orders = rtdb()->get('orders');

    expect($orders)->toBeArray()->toHaveCount(1);

    $id = (string) array_key_first($orders);
    $node = $orders[$id];

    expect($node['type'])->toBe('online')
        ->and($node['status'])->toBe('awaiting_payment')
        ->and($node['total'])->toBe(400.0)
        ->and($node['customerUid'])->toBe('sub-ada')
        ->and($node['code'])->toBeString()->not->toBe('');

    // deadlineAt = placedAt + 15 minutes, exactly.
    $placedAt = \Illuminate\Support\Carbon::parse($node['placedAt']);
    $deadlineAt = \Illuminate\Support\Carbon::parse($node['deadlineAt']);

    expect($placedAt->diffInSeconds($deadlineAt))->toBe(900.0);

    $this->get(route('orders.show', ['order' => $id]))
        ->assertOk()
        ->assertSee((string) $node['code'])
        ->assertSee('0917 123 4567')
        ->assertSee('data:image/jpeg;base64,QRDATA', false);
});

it('returns the original order code on a duplicate and opens no second window', function (): void {
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    addToCart('p1');

    placeOrder('retry-key')->assertRedirect(route('orders.tracker'));

    $first = rtdb()->get('orders');
    $firstId = (string) array_key_first($first);
    $firstCode = $first[$firstId]['code'];
    $firstDeadline = $first[$firstId]['deadlineAt'];

    // A timeout-then-retry replays the same client key.
    placeOrder('retry-key')->assertRedirect(route('orders.show', ['order' => $firstId]));

    $after = rtdb()->get('orders');

    expect($after)->toHaveCount(1)
        ->and($after[$firstId]['code'])->toBe($firstCode)
        ->and($after[$firstId]['deadlineAt'])->toBe($firstDeadline);
});

it('records the idempotency key mapping to the order id', function (): void {
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    addToCart('p1');

    placeOrder('retry-key');

    $orders = rtdb()->get('orders');
    $id = (string) array_key_first($orders);

    expect(rtdb()->get('idem/retry-key')['orderId'] ?? null)->toBe($id);
});

it('shows the tracker with the order code without typing and polls the live state', function (): void {
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    addToCart('p1');

    placeOrder();

    $orders = rtdb()->get('orders');
    $id = (string) array_key_first($orders);
    $code = (string) $orders[$id]['code'];

    $this->get(route('orders.show', ['order' => $id]))
        ->assertOk()
        ->assertSee($code)
        ->assertSee('Awaiting payment')
        ->assertSee(route('orders.state', ['order' => $id]), false)
        ->assertSee('data-poll-seconds="' . OrderTracker::POLL_SECONDS . '"', false);

    $this->get(route('orders.state', ['order' => $id]))
        ->assertOk()
        ->assertJson([
            'code' => $code,
            'status' => 'awaiting_payment',
            'label' => 'Awaiting payment',
        ]);
});

it('reflects a later tracker state once the poll cadence has elapsed', function (): void {
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    addToCart('p1');

    placeOrder();

    $orders = rtdb()->get('orders');
    $id = (string) array_key_first($orders);

    $this->get(route('orders.state', ['order' => $id]))
        ->assertOk()
        ->assertJson(['status' => 'awaiting_payment']);

    // The cashier's Mark served (#26) flips the node; the tracker reads it.
    rtdb()->update("orders/{$id}", ['status' => 'served']);

    $this->travel(OrderTracker::POLL_SECONDS + 1)->seconds();

    $this->get(route('orders.state', ['order' => $id]))
        ->assertOk()
        ->assertJson(['status' => 'served', 'label' => 'READY']);
});

it('coalesces tracker polls so N screens cost one read per cadence', function (): void {
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    addToCart('p1');

    placeOrder();

    $orders = rtdb()->get('orders');
    $id = (string) array_key_first($orders);

    $client = useCountingRtdb();

    $this->get(route('orders.state', ['order' => $id]))->assertOk();
    $this->get(route('orders.state', ['order' => $id]))->assertOk();

    expect($client->reads["orders/{$id}"] ?? 0)->toBe(1);

    $this->travel(OrderTracker::POLL_SECONDS + 1)->seconds();

    $this->get(route('orders.state', ['order' => $id]))->assertOk();

    expect($client->reads["orders/{$id}"] ?? 0)->toBe(2);
});

it('reopens the tracker from the account with zero typing', function (): void {
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    addToCart('p1');

    placeOrder();

    $orders = rtdb()->get('orders');
    $id = (string) array_key_first($orders);
    $code = (string) $orders[$id]['code'];

    // A fresh request on the same account lands on the active order's tracker.
    $this->get(route('orders.tracker'))
        ->assertRedirect(route('orders.show', ['order' => $id]));

    $this->get(route('orders.show', ['order' => $id]))->assertOk()->assertSee($code);
});

it('does not let one customer view another account\'s order', function (): void {
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    addToCart('p1');
    placeOrder();

    $orders = rtdb()->get('orders');
    $id = (string) array_key_first($orders);

    auth()->logout();
    signIn('grace@example.com', 'Grace Hopper', 'sub-grace');

    $this->get(route('orders.show', ['order' => $id]))->assertForbidden();
    $this->get(route('orders.state', ['order' => $id]))->assertForbidden();
});

it('disables ordering while closed and refuses a place order', function (): void {
    seedSettings([
        'hours' => weekdayHours(),
        'forceClose' => true,
        'gcash' => ['number' => '0917 123 4567', 'qr' => 'data:image/jpeg;base64,QRDATA'],
    ]);

    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    addToCart('p1');

    $this->get(route('checkout.show'))
        ->assertOk()
        ->assertSee('<button class="primary-button" type="button" disabled', false)
        ->assertSee('Ordering is closed right now.');

    placeOrder()->assertForbidden();

    expect(rtdb()->get('orders'))->toBeNull();
});

it('disables ordering until the GCash payment block exists and refuses a place order', function (): void {
    seedSettings(['hours' => weekdayHours()]);

    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    addToCart('p1');

    $this->get(route('checkout.show'))
        ->assertOk()
        ->assertSee('<button class="primary-button" type="button" disabled', false)
        ->assertSee('GCash number and QR');

    placeOrder()->assertForbidden();

    expect(rtdb()->get('orders'))->toBeNull();
});

it('stores a scheduled pickup time on the order node, and null for ASAP', function (): void {
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');

    addToCart('p1');
    placeOrder('key-scheduled', '2026-10-06T13:00')->assertRedirect(route('orders.tracker'));

    $orders = rtdb()->get('orders');
    $node = $orders[(string) array_key_first($orders)];

    expect($node['pickupAt'])->toBe('2026-10-06T05:00:00Z');

    addToCart('p1');
    placeOrder('key-asap')->assertRedirect(route('orders.tracker'));

    $orders = rtdb()->get('orders');

    $asap = collect($orders)->firstWhere('pickupAt', null);

    expect($asap)->toBeArray();
});

it('degrades gracefully when the order carries a status the tracker does not know', function (): void {
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    addToCart('p1');
    placeOrder();

    $orders = rtdb()->get('orders');
    $id = (string) array_key_first($orders);

    rtdb()->update("orders/{$id}", ['status' => 'something_new']);
    $this->travel(OrderTracker::POLL_SECONDS + 1)->seconds();

    $this->get(route('orders.state', ['order' => $id]))
        ->assertOk()
        ->assertJson(['status' => 'something_new', 'label' => 'Order status unavailable']);

    $this->get(route('orders.show', ['order' => $id]))
        ->assertOk()
        ->assertSee('Order status unavailable');
});

it('never serves the order node from a cache older than the poll cadence', function (): void {
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    addToCart('p1');
    placeOrder();

    $orders = rtdb()->get('orders');
    $id = (string) array_key_first($orders);

    // The tracker cache entry must expire at the cadence, not later.
    app(\App\Rtdb\Contracts\RtdbClient::class)->cached(
        "order-state:{$id}",
        "orders/{$id}",
        OrderTracker::POLL_SECONDS,
    );

    expect(Cache::has("order-state:{$id}"))->toBeTrue();

    $this->travel(OrderTracker::POLL_SECONDS + 1)->seconds();

    expect(Cache::has("order-state:{$id}"))->toBeFalse();
});
