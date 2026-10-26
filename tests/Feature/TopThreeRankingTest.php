<?php

declare(strict_types=1);

use App\Cache\CatalogCache;
use App\Orders\OrderStatus;
use App\Staff\StaffRole;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * #31 — Top 3 ranking.
 *
 * The ranking is recomputed eagerly on every `sales/{id}` write over the rolling
 * 7-day FIFO window, on the revenue set (served + Unclaimed), cached at
 * `stats/top3` and read by the product-card render. The chip appears once 3
 * distinct products have a sale, always shows the current top ≤ 3 ranked by
 * units with ties broken by revenue then name, reads exactly "Top 3", and an
 * empty window shows no tag. A sale never busts the menu cache.
 */

beforeEach(function (): void {
    seedStaff('maria@example.com', StaffRole::Cashier, 'Maria Santos');
    signIn('maria@example.com', 'Maria Santos');

    seedCategory('drinks', 'Drinks');
    seedProduct('p1', ['name' => 'Milktea', 'categoryId' => 'drinks']);
    seedProduct('p2', ['name' => 'Adobo', 'categoryId' => 'drinks']);
    seedProduct('p3', ['name' => 'Halo-halo', 'categoryId' => 'drinks']);
    seedProduct('p4', ['name' => 'Gamma', 'categoryId' => 'drinks']);
});

/**
 * One order line, exactly as a ring-up freezes it onto the order.
 *
 * @return array{productId: string, name: string, unitPrice: float, quantity: int, addOns: list<array{name: string, price: float}>}
 */
function top3Line(string $productId, string $name, float $unitPrice, int $quantity): array
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
 * Arrange a paid, cooking online order ready for Mark served, carrying lines so
 * the sale it writes can be ranked.
 *
 * @param  list<array<string, mixed>>  $lines
 */
function seedTop3Order(array $lines): string
{
    $id = (string) Str::ulid();

    rtdb()->seed("orders/{$id}", [
        'code' => 'TOP1',
        'type' => 'online',
        'status' => OrderStatus::Cooking->value,
        'lines' => $lines,
        'total' => 100.0,
        'payments' => [['method' => 'gcash', 'amount' => 100.0]],
        'change' => 0.0,
        'customerUid' => 'sub-ada',
        'placedAt' => '2026-10-06T04:00:00Z',
        'proofReceivedAt' => '2026-10-06T04:05:00Z',
        'deadlineAt' => '2026-10-06T04:15:00Z',
    ]);

    return $id;
}

/**
 * Mark a seeded order served through the real cashier route, so the sale write
 * and the ranking recompute are exercised together.
 *
 * @param  list<array<string, mixed>>  $lines
 */
function serveTop3Order(array $lines): string
{
    $id = seedTop3Order($lines);

    test()->post(route('cashier.orders.serve', ['order' => $id]))->assertRedirect(route('cashier.queue'));

    return $id;
}

/**
 * The ranked product ids stored at `stats/top3`, in rank order.
 *
 * @return list<string>
 */
function storedRanking(): array
{
    $ids = [];

    foreach (storedRankingRows() as $row) {
        $ids[] = $row['productId'];
    }

    return $ids;
}

/**
 * The ranked rows stored at `stats/top3`, in rank order.
 *
 * @return list<array<string, mixed>>
 */
function storedRankingRows(): array
{
    $node = rtdb()->get('stats/top3');

    if (! is_array($node) || ! is_array($node['rankings'] ?? null)) {
        return [];
    }

    return array_values(array_filter($node['rankings'], 'is_array'));
}

it('recomputes the ranking when an order is marked served', function (): void {
    serveTop3Order([
        top3Line('p1', 'Milktea', 100.0, 3),
        top3Line('p2', 'Adobo', 100.0, 2),
        top3Line('p3', 'Halo-halo', 100.0, 1),
    ]);

    expect(storedRanking())->toBe(['p1', 'p2', 'p3']);
});

it('recomputes the ranking when the close sweep synthesizes the Unclaimed sale', function (): void {
    seedTop3Order([
        top3Line('p1', 'Milktea', 100.0, 1),
        top3Line('p2', 'Adobo', 100.0, 2),
        top3Line('p3', 'Halo-halo', 100.0, 3),
    ]);

    $this->artisan('orders:close')->assertSuccessful();

    expect(storedRanking())->toBe(['p3', 'p2', 'p1']);
});

it('recomputes the ranking when a walk-in is settled', function (): void {
    $this->post(route('cashier.walkins.store'))->assertRedirect();
    $orders = rtdb()->get('orders');
    $id = (string) array_key_first($orders);

    foreach ([['p1', 3], ['p2', 2], ['p3', 1]] as [$productId, $quantity]) {
        $this->post(
            route('cashier.walkins.items.store', ['walkin' => $id]),
            ['product' => $productId, 'quantity' => $quantity],
        )->assertRedirect();
    }

    $total = rtdb()->get("orders/{$id}")['total'];

    $this->post(route('cashier.walkins.settle', ['walkin' => $id]), ['cash' => (string) $total, 'gcashAmount' => '0'])
        ->assertRedirect(route('cashier.walkins.receipt', ['walkin' => $id]));

    expect(storedRanking())->toBe(['p1', 'p2', 'p3']);
});

it('ranks by units sold, showing the current top three once three products have a sale', function (): void {
    serveTop3Order([
        top3Line('p1', 'Milktea', 100.0, 1),
        top3Line('p2', 'Adobo', 100.0, 3),
        top3Line('p3', 'Halo-halo', 100.0, 2),
    ]);

    expect(storedRanking())->toBe(['p2', 'p3', 'p1']);
});

it('shows no tag before three distinct products have a sale', function (): void {
    serveTop3Order([
        top3Line('p1', 'Milktea', 100.0, 5),
        top3Line('p2', 'Adobo', 100.0, 4),
    ]);

    expect(storedRanking())->toBe([]);

    $this->get('/')->assertOk()->assertDontSee('Top 3');
});

it('shows no tag for an empty window', function (): void {
    $this->get('/')->assertOk()->assertDontSee('Top 3');
});

it('breaks a units tie by revenue, then by name', function (): void {
    // p1 and p2 tie at 2 units; p2 has more revenue so it ranks first. p3 and
    // p4 tie at 1 unit and the same revenue, so the name decides.
    serveTop3Order([
        top3Line('p1', 'Alpha', 100.0, 2),
        top3Line('p2', 'Beta', 150.0, 2),
        top3Line('p3', 'Zeta', 100.0, 1),
        top3Line('p4', 'Gamma', 100.0, 1),
    ]);

    expect(storedRanking())->toBe(['p2', 'p1', 'p4']);
});

it('drops a sale older than the rolling seven-day window', function (): void {
    $this->travelTo(new DateTimeImmutable('2026-10-06 12:00:00', new DateTimeZone('Asia/Manila')));

    // A big early sale of p1 that must age out of the window.
    serveTop3Order([top3Line('p1', 'Milktea', 100.0, 10)]);

    $this->travel(8)->days();

    // A fresh sale of all three products recomputes over a window that no
    // longer contains the early p1 sale, so every product now has 1 unit and
    // the tie is broken by name: Adobo, Halo-halo, Milktea.
    serveTop3Order([
        top3Line('p1', 'Milktea', 100.0, 1),
        top3Line('p2', 'Adobo', 100.0, 1),
        top3Line('p3', 'Halo-halo', 100.0, 1),
    ]);

    expect(storedRanking())->toBe(['p2', 'p3', 'p1']);
});

it('excludes a non-order sale type defensively', function (): void {
    // A rental booking is not an order sale; if it were counted, its 9-unit
    // lines would outrank the real order's single units.
    rtdb()->seed('sales/rental-1', [
        'code' => 'RENT1',
        'type' => 'rental',
        'total' => 500.0,
        'lines' => [
            top3Line('p4', 'Gamma', 500.0, 9),
            top3Line('p2', 'Adobo', 500.0, 9),
            top3Line('p3', 'Halo-halo', 500.0, 9),
        ],
        'soldAt' => now('UTC')->toIso8601ZuluString(),
    ]);

    serveTop3Order([
        top3Line('p1', 'Milktea', 100.0, 3),
        top3Line('p2', 'Adobo', 100.0, 2),
        top3Line('p3', 'Halo-halo', 100.0, 1),
    ]);

    expect(storedRanking())->toBe(['p1', 'p2', 'p3']);
});

it('shows the chip on the ranked product cards, reading exactly "Top 3"', function (): void {
    serveTop3Order([
        top3Line('p1', 'Milktea', 100.0, 3),
        top3Line('p2', 'Adobo', 100.0, 2),
        top3Line('p3', 'Halo-halo', 100.0, 1),
    ]);

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toBeString()
        ->and(substr_count($html, '<span class="tag tag--top">Top 3</span>'))->toBe(3)
        ->and($html)->not->toContain('Top 3 · last 7 days')
        ->and($html)->not->toContain('Top 3 days');
});

it('shows the chip on the product page for a ranked product', function (): void {
    serveTop3Order([
        top3Line('p1', 'Milktea', 100.0, 3),
        top3Line('p2', 'Adobo', 100.0, 2),
        top3Line('p3', 'Halo-halo', 100.0, 1),
    ]);

    $this->get(route('menu.product', ['product' => 'p1']))
        ->assertOk()
        ->assertSee('<span class="tag tag--top">Top 3</span>', false);
});

it('does not bust the menu cache when a sale is written', function (): void {
    seedSettings(['hours' => weekdayHours()]);

    // Seed the order to serve before wrapping the client in the read counter.
    $orderId = seedTop3Order([
        top3Line('p1', 'Milktea', 100.0, 3),
        top3Line('p2', 'Adobo', 100.0, 2),
        top3Line('p3', 'Halo-halo', 100.0, 1),
    ]);

    $client = useCountingRtdb();

    // The menu is read once and cached.
    $this->get('/')->assertOk();

    expect($client->reads['products'] ?? 0)->toBe(1);

    // A sale write recomputes the ranking but must not evict the base64 catalog.
    $this->post(route('cashier.orders.serve', ['order' => $orderId]))
        ->assertRedirect(route('cashier.queue'));

    $this->get('/')->assertOk()->assertSee('Top 3');

    expect($client->reads['products'] ?? 0)->toBe(1)
        ->and(Cache::has(CatalogCache::KEY))->toBeTrue();
});
