<?php

declare(strict_types=1);

use App\Rtdb\Contracts\RtdbClient;
use App\Rtdb\InMemoryRtdbClient;
use App\Rtdb\RtdbQuery;

it('binds the in-memory client in the container during tests', function (): void {
    expect(app(RtdbClient::class))->toBeInstanceOf(InMemoryRtdbClient::class);
});

it('rejects a path containing a key RTDB forbids', function (): void {
    $client = app(RtdbClient::class);

    expect(fn() => $client->set('orders/o.1', ['status' => 'placed']))
        ->toThrow(InvalidArgumentException::class);

    expect(fn() => $client->get('orders/o#1'))
        ->toThrow(InvalidArgumentException::class);

    expect(fn() => $client->query('orders/o$1', RtdbQuery::orderBy('status')->equalTo('placed')))
        ->toThrow(InvalidArgumentException::class);
});
