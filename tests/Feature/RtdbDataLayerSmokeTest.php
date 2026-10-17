<?php

declare(strict_types=1);

use App\Rtdb\Contracts\RtdbClient;
use App\Rtdb\RtdbQuery;
use App\Rtdb\Rules\RulesFixture;

beforeEach(function (): void {
    $this->rules = RulesFixture::fromFile(base_path('database.rules.json'));
});

it('gates every read and write on an authenticated service account', function (): void {
    expect($this->rules->read())->toBe('auth != null')
        ->and($this->rules->write())->toBe('auth != null');
});

it('declares an index for every field the data layer queries', function (): void {
    $queries = [
        ['orders', 'status'],
        ['orders', 'deadlineAt'],
        ['orders', 'updatedAt'],
        ['orders', 'settledAt'],
        ['proofs', 'rejectedAt'],
        ['reservations', 'phone'],
        ['reservations', 'date'],
        ['products', 'updatedAt'],
        ['rentalBookings', 'updatedAt'],
        ['rentalBookings', 'dateFrom'],
        ['rentalBookings', 'status'],
        ['rentalBookings', 'uid'],
        ['rentalBookings', 'deadlineAt'],
        ['rentalBookings', 'terminalAt'],
        ['sales', 'updatedAt'],
        ['sales', 'soldAt'],
        ['staff', 'email'],
        ['audit', 'orderId'],
        ['audit', 'updatedAt'],
        ['idem', 'updatedAt'],
    ];

    foreach ($queries as [$path, $field]) {
        expect(in_array($field, $this->rules->indexes($path), true))
            ->toBeTrue("{$path} must declare .indexOn for {$field}");
    }
});

it('rejects a query on a field that has no declared index', function (): void {
    expect(fn() => $this->rules->assertIndexed('orders', 'refundOwed'))
        ->toThrow(RuntimeException::class);
});

it('round-trips a write and stamps updatedAt on the stored node', function (): void {
    $this->travelTo(new DateTimeImmutable('2026-10-06 04:12:23', new DateTimeZone('UTC')));

    $client = app(RtdbClient::class);
    $client->set('products/p1', ['name' => 'Milktea', 'category' => 'drinks']);

    expect($client->get('products/p1'))
        ->toMatchArray(['name' => 'Milktea', 'updatedAt' => '2026-10-06T04:12:23Z']);
});

it('merges a partial update without dropping the sibling fields', function (): void {
    $client = app(RtdbClient::class);
    $client->set('orders/o1', ['status' => 'placed', 'total' => 250]);
    $client->update('orders/o1', ['status' => 'cooking']);

    expect($client->get('orders/o1'))->toMatchArray(['status' => 'cooking', 'total' => 250]);
});

it('deletes a node so a later read returns null', function (): void {
    $client = app(RtdbClient::class);
    $client->set('proofs/o1', ['image' => 'bytes']);
    $client->delete('proofs/o1');

    expect($client->get('proofs/o1'))->toBeNull();
});

it('returns only the rows whose indexed field matches an equality query', function (): void {
    $client = app(RtdbClient::class);
    $client->set('orders/o1', ['status' => 'cooking', 'total' => 100]);
    $client->set('orders/o2', ['status' => 'served', 'total' => 200]);
    $client->set('orders/o3', ['status' => 'cooking', 'total' => 300]);

    $rows = $client->query('orders', RtdbQuery::orderBy('status')->equalTo('cooking'));

    expect(array_keys($rows))->toBe(['o1', 'o3'])
        ->and($rows['o1']['total'])->toBe(100);
});

it('bounds a range query by an indexed field', function (): void {
    $client = app(RtdbClient::class);
    $client->set('orders/o1', ['deadlineAt' => '2026-10-06T04:00:00Z']);
    $client->set('orders/o2', ['deadlineAt' => '2026-10-06T05:00:00Z']);
    $client->set('orders/o3', ['deadlineAt' => '2026-10-07T05:00:00Z']);

    $rows = $client->query(
        'orders',
        RtdbQuery::orderBy('deadlineAt')->range('2026-10-06T04:30:00Z', '2026-10-06T23:59:59Z'),
    );

    expect(array_keys($rows))->toBe(['o2']);
});
