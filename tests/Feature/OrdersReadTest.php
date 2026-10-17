<?php

declare(strict_types=1);

use App\Orders\Order;
use App\Orders\OrderNotFound;
use App\Orders\Orders;
use App\Orders\OrderStatus;

/**
 * The order read seam: {@see Orders::require()} is the load-and-parse every
 * action routes through, and its not-found branch is the one place a
 * caller-supplied missing id is turned into a failure.
 */

it('returns the order for an id that exists', function (): void {
    rtdb()->seed('orders/o1', ['code' => 'ORD1', 'type' => 'online', 'status' => 'cooking']);

    $order = app(Orders::class)->require('o1');

    expect($order->id)->toBe('o1')
        ->and($order->code)->toBe('ORD1');
});

it('throws OrderNotFound for an id that does not exist', function (): void {
    expect(fn() => app(Orders::class)->require('missing'))
        ->toThrow(OrderNotFound::class, 'No order with id [missing].');
});

it('returns the orders in a status, keyed by id', function (): void {
    rtdb()->seed('orders/o1', ['code' => 'A1', 'type' => 'online', 'status' => 'cooking']);
    rtdb()->seed('orders/o2', ['code' => 'A2', 'type' => 'online', 'status' => 'cooking']);
    rtdb()->seed('orders/o3', ['code' => 'A3', 'type' => 'online', 'status' => 'served']);

    $orders = app(Orders::class)->byStatus(OrderStatus::Cooking);

    expect(array_keys($orders))->toBe(['o1', 'o2'])
        ->and($orders['o1'])->toBeInstanceOf(Order::class);
});

it('skips a row in the status that cannot be parsed', function (): void {
    rtdb()->seed('orders/o1', ['code' => 'A1', 'type' => 'online', 'status' => 'cooking']);
    // No code: the row carries the queried status but cannot become an Order.
    rtdb()->seed('orders/bad', ['type' => 'online', 'status' => 'cooking']);

    $orders = app(Orders::class)->byStatus(OrderStatus::Cooking);

    expect(array_keys($orders))->toBe(['o1']);
});
