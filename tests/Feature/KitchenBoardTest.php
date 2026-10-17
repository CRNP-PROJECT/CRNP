<?php

declare(strict_types=1);

use App\Cache\KitchenBoardCache;
use App\Orders\Actions\MarkServed;
use App\Orders\HandledBy;
use App\Orders\OrderStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * #28 — the kitchen read-only board.
 *
 * `/kitchen` is a shared-secret URL with no login and zero cook interaction.
 * NOW is oldest-first by ready-for with age timers (red at 12 min), a NEW badge
 * (genuine new tickets, until they age past 3 min), and all-day counts over NOW
 * only. LATER holds scheduled pickups as dimmed rows with countdowns; the
 * server promotes at `pickup − 15 min` or at verify, whichever is later. A
 * promoted ticket joins NOW by age, shows both clocks, and reads LATE
 * immediately if overdue. The board is a fixed 5 s fetch served from the
 * coalesced snapshot, and a ticket clears on Mark served, Reject, or
 * ready-for + 15 min (or close).
 */

beforeEach(function (): void {
    seedSettings(['kitchenSecret' => 'k-secret-123']);

    // 2026-10-06 12:00 Asia/Manila is 04:00 UTC — every instant below is
    // expressed against this clock.
    $this->travelTo(Carbon::parse('2026-10-06 12:00:00', 'Asia/Manila'));
});

function kitchenUrl(string $secret = 'k-secret-123'): string
{
    return "/kitchen/{$secret}";
}

/**
 * Arrange an order in the kitchen set (verified, unserved) exactly as #24/#26
 * leave it, defaulting to an ASAP online pickup verified 30 minutes ago.
 *
 * @param  array<string, mixed>  $overrides
 */
function kitchenOrder(array $overrides = []): string
{
    $id = (string) Str::ulid();

    rtdb()->seed("orders/{$id}", [
        'code' => 'KIT1',
        'type' => 'online',
        'status' => OrderStatus::Cooking->value,
        'lines' => [],
        'total' => 100.0,
        'payments' => [],
        'change' => 0.0,
        'customerUid' => 'sub-ada',
        'placedAt' => '2026-10-06T03:30:00Z',
        'pickupAt' => null,
        'deadlineAt' => '2026-10-06T03:45:00Z',
        'verifiedAt' => '2026-10-06T03:50:00Z',
        ...$overrides,
    ]);

    return $id;
}

it('opens the board by shared-secret URL with no login', function (): void {
    kitchenOrder(['code' => 'OPEN1']);

    $this->get(kitchenUrl())
        ->assertOk()
        ->assertSee('OPEN1');
});

it('accepts the secret as a query string too', function (): void {
    kitchenOrder(['code' => 'QUERY1']);

    $this->get('/kitchen?secret=k-secret-123')->assertOk()->assertSee('QUERY1');
    $this->get('/kitchen/state?secret=k-secret-123')->assertOk();
});

it('refuses a wrong or missing secret', function (): void {
    $this->get(kitchenUrl('nope'))->assertNotFound();
    $this->get('/kitchen')->assertNotFound();
    $this->get(kitchenUrl('nope') . '/state')->assertNotFound();
});

it('refuses every URL when no kitchen secret is configured', function (): void {
    seedSettings(['kitchenSecret' => '']);

    $this->get(kitchenUrl('anything'))->assertNotFound();
});

it('kills the old URL when the secret rotates', function (): void {
    kitchenOrder(['code' => 'ROT1']);

    $this->get(kitchenUrl('old-secret'))->assertNotFound();
    seedSettings(['kitchenSecret' => 'old-secret']);
    $this->get(kitchenUrl('old-secret'))->assertOk();

    seedSettings(['kitchenSecret' => 'new-secret']);

    $this->get(kitchenUrl('old-secret'))->assertNotFound();
    $this->get(kitchenUrl('new-secret'))->assertOk();
});

it('shows only verified, unserved orders', function (): void {
    kitchenOrder(['code' => 'COOK1', 'status' => OrderStatus::Cooking->value]);
    kitchenOrder(['code' => 'WAIT1', 'status' => OrderStatus::AwaitingPayment->value]);
    kitchenOrder(['code' => 'SERV1', 'status' => OrderStatus::Served->value]);
    kitchenOrder(['code' => 'REJ1', 'status' => OrderStatus::Rejected->value]);
    kitchenOrder(['code' => 'PEND1', 'type' => 'walkin', 'status' => OrderStatus::Pending->value, 'settledAt' => null]);

    $this->get(kitchenUrl())
        ->assertOk()
        ->assertSee('COOK1')
        ->assertDontSee('WAIT1')
        ->assertDontSee('SERV1')
        ->assertDontSee('REJ1')
        ->assertDontSee('PEND1');
});

it('derives a walk-in ready-for from its settle instant plus the cook lead', function (): void {
    // settled 03:50 → ready-for 04:05; its age origin is the settle instant.
    kitchenOrder([
        'code' => 'WALK1',
        'type' => 'walkin',
        'verifiedAt' => null,
        'settledAt' => '2026-10-06T03:50:00Z',
    ]);

    $state = $this->get(kitchenUrl() . '/state')->assertOk()->json();
    $ticket = collect($state['now'])->firstWhere('code', 'WALK1');

    expect($ticket)->not->toBeNull()
        ->and($ticket['readyFor'])->toBe('2026-10-06T04:05:00Z')
        ->and($ticket['cookableAt'])->toBe('2026-10-06T03:50:00Z')
        ->and($ticket['ageSeconds'])->toBe(10 * 60)
        ->and($ticket['promoted'])->toBeFalse()
        ->and($ticket['new'])->toBeFalse();
});

it('never reuses the NEW flash on a promoted scheduled pickup', function (): void {
    // Just promoted (pickup − 15 min = now) and well within the NEW window by
    // age, but a promoted ticket is not a genuine new one.
    kitchenOrder(['code' => 'PROMONEW', 'pickupAt' => '2026-10-06T04:15:00Z', 'verifiedAt' => '2026-10-06T03:20:00Z']);

    $state = $this->get(kitchenUrl() . '/state')->assertOk()->json();
    $ticket = collect($state['now'])->firstWhere('code', 'PROMONEW');

    expect($ticket)->not->toBeNull()
        ->and($ticket['promoted'])->toBeTrue()
        ->and($ticket['new'])->toBeFalse();
});

it('orders NOW oldest-first by age', function (): void {
    // Both ASAP: age origin is verify. The earlier verify is the older ticket
    // and must come first.
    kitchenOrder(['code' => 'AAA1', 'verifiedAt' => '2026-10-06T03:35:00Z']);
    kitchenOrder(['code' => 'BBB1', 'verifiedAt' => '2026-10-06T03:40:00Z']);

    $this->get(kitchenUrl())
        ->assertOk()
        ->assertSeeInOrder(['AAA1', 'BBB1']);
});

it('marks a ticket red once its age passes twelve minutes', function (): void {
    // ready-for = now − 13 min → age 13 min (red); the other is 5 min (not red).
    kitchenOrder(['code' => 'RED1', 'verifiedAt' => '2026-10-06T03:32:00Z']);
    kitchenOrder(['code' => 'CALM1', 'verifiedAt' => '2026-10-06T03:55:00Z']);

    $state = $this->get(kitchenUrl() . '/state')->assertOk()->json();

    $byCode = collect($state['now'])->keyBy('code');

    expect($byCode['RED1']['red'])->toBeTrue()
        ->and($byCode['RED1']['ageSeconds'])->toBeGreaterThanOrEqual(12 * 60)
        ->and($byCode['CALM1']['red'])->toBeFalse();
});

it('shows a NEW badge on a genuine new ticket until it ages past three minutes', function (): void {
    // A walk-in and an ASAP order are genuinely new; the older ASAP one is not.
    kitchenOrder(['code' => 'NEW1', 'verifiedAt' => '2026-10-06T03:59:00Z']);
    kitchenOrder(['code' => 'OLD1', 'verifiedAt' => '2026-10-06T03:50:00Z']);
    kitchenOrder(['code' => 'NEWALK', 'type' => 'walkin', 'settledAt' => '2026-10-06T03:59:00Z', 'verifiedAt' => null]);

    $state = $this->get(kitchenUrl() . '/state')->assertOk()->json();
    $byCode = collect($state['now'])->keyBy('code');

    expect($byCode['NEW1']['new'])->toBeTrue()
        ->and($byCode['NEWALK']['new'])->toBeTrue()
        ->and($byCode['OLD1']['new'])->toBeFalse();

    $this->get(kitchenUrl())->assertOk()->assertSee('NEW');
});

it('counts all-day quantities over NOW only, never LATER', function (): void {
    kitchenOrder([
        'code' => 'NOWQ',
        'verifiedAt' => '2026-10-06T03:50:00Z',
        'lines' => [
            ['productId' => 'p1', 'name' => 'Milktea', 'unitPrice' => 100.0, 'quantity' => 2, 'addOns' => []],
            ['productId' => 'p2', 'name' => 'Fries', 'unitPrice' => 50.0, 'quantity' => 1, 'addOns' => []],
        ],
    ]);
    // A scheduled pickup still in LATER — its 5 milkteas must not be counted.
    kitchenOrder([
        'code' => 'LATQ',
        'pickupAt' => '2026-10-06T04:30:00Z',
        'verifiedAt' => '2026-10-06T03:50:00Z',
        'lines' => [
            ['productId' => 'p1', 'name' => 'Milktea', 'unitPrice' => 100.0, 'quantity' => 5, 'addOns' => []],
        ],
    ]);

    $state = $this->get(kitchenUrl() . '/state')->assertOk()->json();
    $allDay = collect($state['allDay'])->keyBy('name');

    expect($allDay['Milktea']['quantity'])->toBe(2)
        ->and($allDay['Fries']['quantity'])->toBe(1)
        ->and($state['counts']['now'])->toBe(1)
        ->and($state['counts']['later'])->toBe(1);
});

it('holds a scheduled pickup in LATER, dimmed, with a countdown', function (): void {
    // pickup − 15 min is still ahead, so the ticket waits in LATER.
    kitchenOrder([
        'code' => 'LATER1',
        'pickupAt' => '2026-10-06T04:30:00Z',
        'verifiedAt' => '2026-10-06T03:50:00Z',
    ]);

    $this->get(kitchenUrl())
        ->assertOk()
        ->assertSee('LATER')
        ->assertSee('LATER1')
        ->assertSee('data-ready-in', false)
        ->assertSee('data-ready-for', false)
        ->assertSee('kitchen-card--later', false);

    $state = $this->get(kitchenUrl() . '/state')->assertOk()->json();

    expect(collect($state['later'])->pluck('code'))->toContain('LATER1')
        ->and(collect($state['now'])->pluck('code'))->not->toContain('LATER1');
});

it('promotes a scheduled pickup at pickup minus fifteen minutes', function (): void {
    // pickup 04:15 → promotion 04:00 (now); pickup 04:16 → promotion 04:01.
    kitchenOrder(['code' => 'PROMO1', 'pickupAt' => '2026-10-06T04:15:00Z', 'verifiedAt' => '2026-10-06T03:50:00Z']);
    kitchenOrder(['code' => 'PROMO2', 'pickupAt' => '2026-10-06T04:16:00Z', 'verifiedAt' => '2026-10-06T03:50:00Z']);

    $state = $this->get(kitchenUrl() . '/state')->assertOk()->json();

    expect(collect($state['now'])->pluck('code'))->toContain('PROMO1')
        ->and(collect($state['later'])->pluck('code'))->toContain('PROMO2');

    $this->travel(2)->minutes();

    $state = $this->get(kitchenUrl() . '/state')->assertOk()->json();

    expect(collect($state['now'])->pluck('code'))->toContain('PROMO2');
});

it('promotes at verify when verify is later than pickup minus fifteen minutes', function (): void {
    // pickup 04:10 → cook lead start 03:55, but verify is at 04:00 (later), so
    // promotion is at verify: it is in NOW the instant it verifies.
    kitchenOrder(['code' => 'VERPROM', 'pickupAt' => '2026-10-06T04:10:00Z', 'verifiedAt' => '2026-10-06T04:00:00Z']);

    $state = $this->get(kitchenUrl() . '/state')->assertOk()->json();

    expect(collect($state['now'])->pluck('code'))->toContain('VERPROM');
});

it('shows both clocks on a promoted ticket and reads LATE immediately if overdue', function (): void {
    // pickup 03:55 is already past: promoted, overdue, and its age is not 0:00.
    kitchenOrder(['code' => 'LATE1', 'pickupAt' => '2026-10-06T03:55:00Z', 'verifiedAt' => '2026-10-06T03:40:00Z']);

    $state = $this->get(kitchenUrl() . '/state')->assertOk()->json();
    $ticket = collect($state['now'])->firstWhere('code', 'LATE1');

    expect($ticket)->not->toBeNull()
        ->and($ticket['late'])->toBeTrue()
        ->and($ticket['ageSeconds'])->toBeGreaterThanOrEqual(5 * 60)
        ->and($ticket['readyFor'])->toBe('2026-10-06T03:55:00Z');

    // Both clocks ride on the card: the age timer and the ready-for instant.
    $this->get(kitchenUrl())
        ->assertOk()
        ->assertSee('LATE1')
        ->assertSee('data-ready-for="2026-10-06T03:55:00Z"', false)
        ->assertSee('LATE');
});

it('clears a ticket at ready-for plus fifteen minutes', function (): void {
    // ready-for 03:44 (past +15) is gone; ready-for 03:46 is still held.
    kitchenOrder(['code' => 'GONE1', 'verifiedAt' => '2026-10-06T03:29:00Z']);
    kitchenOrder(['code' => 'HELD1', 'verifiedAt' => '2026-10-06T03:31:00Z']);

    $this->get(kitchenUrl())
        ->assertOk()
        ->assertDontSee('GONE1')
        ->assertSee('HELD1');
});

it('clears a served ticket the same instant it is marked served', function (): void {
    $id = kitchenOrder(['code' => 'SERVE1', 'verifiedAt' => '2026-10-06T03:55:00Z']);

    $this->get(kitchenUrl())->assertOk()->assertSee('SERVE1');

    app(MarkServed::class)($id, new HandledBy('u1', 'Maria'));

    $this->get(kitchenUrl())->assertOk()->assertDontSee('SERVE1');
});

it('clears a rejected ticket the same instant the proof is refused', function (): void {
    // #27's Reject flips the status; the board mirrors verified, unserved only.
    kitchenOrder(['code' => 'REJECT1', 'status' => OrderStatus::Rejected->value, 'verifiedAt' => '2026-10-06T03:55:00Z']);

    $this->get(kitchenUrl())->assertOk()->assertDontSee('REJECT1');
});

it('clears a ticket the close sweep decides Unclaimed', function (): void {
    kitchenOrder(['code' => 'CLOSE1', 'verifiedAt' => '2026-10-06T03:55:00Z']);

    $this->get(kitchenUrl())->assertOk()->assertSee('CLOSE1');

    // The close sweep (#25) flips a paid, uncollected order to `unclaimed`;
    // the board reads `cooking`, so the ticket drops with no board write. The
    // coalesced entry reflects it within the accepted 5 s cadence.
    app(\App\Orders\CloseSweep::class)();

    $this->travel(KitchenBoardCache::TTL_SECONDS + 1)->seconds();

    $this->get(kitchenUrl())->assertOk()->assertDontSee('CLOSE1');
});

it('serves the board from the coalesced snapshot', function (): void {
    kitchenOrder(['code' => 'CACHE1', 'verifiedAt' => '2026-10-06T03:55:00Z']);

    $client = useCountingRtdb();

    $this->get(kitchenUrl())->assertOk()->assertSee('CACHE1');
    $this->get(kitchenUrl() . '/state')->assertOk();
    $this->get(kitchenUrl() . '/state')->assertOk();

    expect($client->queries['orders'] ?? 0)->toBe(1);

    // Past the cadence the entry lapses and RTDB is read once more.
    $this->travel(KitchenBoardCache::TTL_SECONDS + 1)->seconds();

    $this->get(kitchenUrl() . '/state')->assertOk();

    expect($client->queries['orders'] ?? 0)->toBe(2);
});

it('shows a Reconnecting banner and the stale threshold', function (): void {
    kitchenOrder(['code' => 'HB1', 'verifiedAt' => '2026-10-06T03:55:00Z']);

    $this->get(kitchenUrl())
        ->assertOk()
        ->assertSee('Reconnecting…')
        ->assertSee('data-poll-seconds="5"', false)
        ->assertSee('data-stale-seconds="15"', false)
        ->assertSee('data-poll-url="' . route('kitchen.state.secret', ['secret' => 'k-secret-123']) . '"', false);
});

it('never carries proof image bytes in the board read', function (): void {
    $id = kitchenOrder(['code' => 'IMG1', 'verifiedAt' => '2026-10-06T03:55:00Z']);

    rtdb()->seed("proofs/{$id}", [
        'orderId' => $id,
        'imageBase64' => 'data:image/jpeg;base64,PROOFBYTES',
        'amount' => 100.0,
    ]);

    $html = $this->get(kitchenUrl())->assertOk()->getContent();
    $json = json_encode($this->get(kitchenUrl() . '/state')->assertOk()->json());

    expect($html)->toBeString()
        ->and($html)->not->toContain('imageBase64')
        ->and($html)->not->toContain('PROOFBYTES')
        ->and($json)->toBeString()
        ->and($json)->not->toContain('imageBase64')
        ->and($json)->not->toContain('PROOFBYTES');
});

it('honors prefers-reduced-motion and keeps the NEW flash under three per second', function (): void {
    kitchenOrder(['code' => 'MOTION1', 'verifiedAt' => '2026-10-06T03:59:00Z']);

    $this->get(kitchenUrl())
        ->assertOk()
        ->assertSee('prefers-reduced-motion', false);
});

it('evicts the board the same instant a proof is rejected, without waiting out the TTL', function (): void {
    $id = kitchenOrder(['code' => 'DROP1', 'verifiedAt' => '2026-10-06T03:55:00Z']);

    // Warm the coalesced board entry.
    $this->get(kitchenUrl())->assertOk()->assertSee('DROP1');

    app(\App\Orders\Actions\RejectOrder::class)($id, new HandledBy('u1', 'Maria'));

    // No time travel: Reject evicts the board entry, so the ticket is gone on
    // the very next read rather than lingering for the 5 s TTL.
    $this->get(kitchenUrl())->assertOk()->assertDontSee('DROP1');
});

it('evicts the board the same instant a flagged hold is approved, without waiting out the TTL', function (): void {
    $id = kitchenOrder([
        'code' => 'ADD1',
        'status' => OrderStatus::Verifying->value,
        'enteredAmount' => 90.0,
        'flaggedAt' => '2026-10-06T03:55:00Z',
    ]);

    // Warm the coalesced board entry (the hold is not on it yet).
    $this->get(kitchenUrl())->assertOk()->assertDontSee('ADD1');

    app(\App\Orders\Actions\ApproveOrder::class)($id);

    // No time travel: Approve evicts the board entry, so the promoted ticket
    // appears on the very next read rather than after the 5 s TTL.
    $this->get(kitchenUrl())->assertOk()->assertSee('ADD1');
});

it('renders each line\'s add-ons on the kitchen ticket', function (): void {
    kitchenOrder([
        'code' => 'ONION1',
        'verifiedAt' => '2026-10-06T03:55:00Z',
        'lines' => [[
            'productId' => 'p1',
            'name' => 'Milktea',
            'unitPrice' => 100.0,
            'quantity' => 2,
            'addOns' => [
                ['name' => 'Extra pearls', 'price' => 20.0],
                ['name' => 'Less sugar', 'price' => 0.0],
            ],
        ]],
    ]);

    $html = $this->get(kitchenUrl())->assertOk()->getContent();
    $state = $this->get(kitchenUrl() . '/state')->assertOk()->json();

    // The add-on names ride on the ticket under the line.
    expect($html)->toBeString()
        ->and($html)->toContain('Extra pearls')
        ->and($html)->toContain('Less sugar')
        ->and($state['now'][0]['lines'][0]['addOns'][0]['name'])->toBe('Extra pearls');
});
