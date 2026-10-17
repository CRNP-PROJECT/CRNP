<?php

declare(strict_types=1);

use App\Rtdb\RtdbQuery;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('reads a path and authenticates with a bearer token', function (): void {
    fakeRtdb([
        RTDB_BASE_URL . '/products/p1.json' => Http::response(['name' => 'Milktea']),
    ]);

    $value = restRtdbClient()->get('products/p1');

    expect($value)->toBe(['name' => 'Milktea']);

    Http::assertSent(function (Request $request): bool {
        return $request->method() === 'GET'
            && $request->url() === RTDB_BASE_URL . '/products/p1.json'
            && $request->hasHeader('Authorization', 'Bearer test-token');
    });
});

it('mints the access token once and reuses it for later calls', function (): void {
    fakeRtdb([
        RTDB_BASE_URL . '/a.json' => Http::response(['ok' => true]),
        RTDB_BASE_URL . '/b.json' => Http::response(['ok' => true]),
    ]);

    $client = restRtdbClient();
    $client->get('a');
    $client->get('b');

    Http::assertSentCount(3); // one token mint + two reads
});

it('treats an empty node as null', function (): void {
    fakeRtdb([
        RTDB_BASE_URL . '/empty.json' => Http::response(null),
    ]);

    expect(restRtdbClient()->get('empty'))->toBeNull();
});

it('drops an error body on read instead of returning it as data', function (): void {
    fakeRtdb([
        RTDB_BASE_URL . '/orders.json' => Http::response(['error' => 'Index not defined']),
    ]);

    expect(restRtdbClient()->get('orders'))->toBe([]);
});

it('writes a node with put and stamps updatedAt', function (): void {
    fakeRtdb([
        RTDB_BASE_URL . '/settings.json' => Http::response(['updatedAt' => 'stamped']),
    ]);

    $this->travelTo(new DateTimeImmutable('2026-10-06 04:12:23', new DateTimeZone('UTC')));

    restRtdbClient()->set('settings', ['banner' => 'Hello']);

    Http::assertSent(function (Request $request): bool {
        if ($request->method() !== 'PUT' || $request->url() !== RTDB_BASE_URL . '/settings.json') {
            return false;
        }

        $body = $request->data();

        return $body['banner'] === 'Hello'
            && $body['updatedAt'] === '2026-10-06T04:12:23Z';
    });
});

it('merges named children with patch and stamps updatedAt', function (): void {
    fakeRtdb([
        RTDB_BASE_URL . '/orders/o1.json' => Http::response(['status' => 'cooking']),
    ]);

    $this->travelTo(new DateTimeImmutable('2026-10-06 05:00:00', new DateTimeZone('UTC')));

    restRtdbClient()->update('orders/o1', ['status' => 'cooking']);

    Http::assertSent(function (Request $request): bool {
        if ($request->method() !== 'PATCH' || $request->url() !== RTDB_BASE_URL . '/orders/o1.json') {
            return false;
        }

        $body = $request->data();

        return $body['status'] === 'cooking'
            && $body['updatedAt'] === '2026-10-06T05:00:00Z';
    });
});

it('deletes a node with delete', function (): void {
    fakeRtdb([
        RTDB_BASE_URL . '/proofs/o1.json' => Http::response(null),
    ]);

    restRtdbClient()->delete('proofs/o1');

    Http::assertSent(fn(Request $request): bool => $request->method() === 'DELETE'
        && $request->url() === RTDB_BASE_URL . '/proofs/o1.json');
});

it('throws when a write returns an error body', function (): void {
    fakeRtdb([
        RTDB_BASE_URL . '/orders/o1.json' => Http::response(['error' => 'Permission denied'], 401),
    ]);

    restRtdbClient()->update('orders/o1', ['status' => 'cooking']);
})->throws(RuntimeException::class);

it('builds an equality query on an indexed field', function (): void {
    fakeRtdb([
        RTDB_BASE_URL . '/orders.json*' => Http::response(['o1' => ['status' => 'cooking']]),
    ]);

    $rows = restRtdbClient()->query('orders', RtdbQuery::orderBy('status')->equalTo('cooking'));

    expect($rows)->toBe(['o1' => ['status' => 'cooking']]);

    Http::assertSent(function (Request $request): bool {
        if ($request->method() !== 'GET' || ! str_starts_with($request->url(), RTDB_BASE_URL . '/orders.json?')) {
            return false;
        }

        $query = httpQuery($request);

        return $query['orderBy'] === '"status"'
            && $query['equalTo'] === '"cooking"';
    });
});

it('builds a prefix query bounded by the sentinel', function (): void {
    fakeRtdb([
        RTDB_BASE_URL . '/reservations.json*' => Http::response([]),
    ]);

    restRtdbClient()->query('reservations', RtdbQuery::orderBy('phone')->startsWith('0917'));

    Http::assertSent(function (Request $request): bool {
        if ($request->method() !== 'GET' || ! str_starts_with($request->url(), RTDB_BASE_URL . '/reservations.json?')) {
            return false;
        }

        $query = httpQuery($request);

        return $query['orderBy'] === '"phone"'
            && $query['startAt'] === '"0917"'
            && $query['endAt'] === '"0917\\uf8ff"';
    });
});

it('builds a bounded range query with a limit', function (): void {
    fakeRtdb([
        RTDB_BASE_URL . '/orders.json*' => Http::response([]),
    ]);

    restRtdbClient()->query(
        'orders',
        RtdbQuery::orderBy('deadlineAt')
            ->range('2026-10-06T00:00:00Z', '2026-10-06T23:59:59Z')
            ->limitToFirst(20),
    );

    Http::assertSent(function (Request $request): bool {
        if ($request->method() !== 'GET' || ! str_starts_with($request->url(), RTDB_BASE_URL . '/orders.json?')) {
            return false;
        }

        $query = httpQuery($request);

        return $query['orderBy'] === '"deadlineAt"'
            && $query['startAt'] === '"2026-10-06T00:00:00Z"'
            && $query['endAt'] === '"2026-10-06T23:59:59Z"'
            && $query['limitToFirst'] === '20';
    });
});

it('builds an open-ended startAt query for the export watermark', function (): void {
    fakeRtdb([
        RTDB_BASE_URL . '/sales.json*' => Http::response([]),
    ]);

    restRtdbClient()->query(
        'sales',
        RtdbQuery::orderBy('updatedAt')->startAt('2026-10-06T00:00:00Z')->limitToFirst(500),
    );

    Http::assertSent(function (Request $request): bool {
        if ($request->method() !== 'GET' || ! str_starts_with($request->url(), RTDB_BASE_URL . '/sales.json?')) {
            return false;
        }

        $query = httpQuery($request);

        return $query['orderBy'] === '"updatedAt"'
            && $query['startAt'] === '"2026-10-06T00:00:00Z"'
            && ! isset($query['endAt'])
            && $query['limitToFirst'] === '500';
    });
});

it('serves a cached read without re-reading inside the poll cadence', function (): void {
    fakeRtdb([
        RTDB_BASE_URL . '/products.json' => Http::response(['p1' => ['name' => 'Milktea']]),
    ]);

    $client = restRtdbClient();
    $client->cached('catalog', 'products', 60);
    $client->cached('catalog', 'products', 60);

    Http::assertSentCount(2); // one token mint + one read
});

it('re-reads once the poll cadence has elapsed', function (): void {
    fakeRtdb([
        RTDB_BASE_URL . '/products.json' => Http::response(['p1' => ['name' => 'Milktea']]),
    ]);

    $client = restRtdbClient();
    $client->cached('catalog', 'products', 60);

    $this->travel(61)->seconds();

    $client->cached('catalog', 'products', 60);

    Http::assertSentCount(3); // token mint + two reads
});

it('forgets a cached read on demand', function (): void {
    fakeRtdb([
        RTDB_BASE_URL . '/products.json' => Http::response(['p1' => ['name' => 'Milktea']]),
    ]);

    $client = restRtdbClient();
    $client->cached('catalog', 'products', 60);
    $client->forget('catalog');
    $client->cached('catalog', 'products', 60);

    Http::assertSentCount(3); // token mint + two reads
});
