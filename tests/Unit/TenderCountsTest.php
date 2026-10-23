<?php

declare(strict_types=1);

use App\Stats\TenderCounts;

it('counts orders paid by cash and by GCash, counting a split on both sides', function (): void {
    $counts = TenderCounts::fromSales([
        ['type' => 'walkin', 'total' => 200.0, 'payments' => [['method' => 'cash', 'amount' => 200.0]]],
        ['type' => 'walkin', 'total' => 150.0, 'payments' => [['method' => 'gcash', 'amount' => 150.0]]],
        ['type' => 'online', 'total' => 400.0, 'payments' => []],
        [
            'type' => 'walkin',
            'total' => 100.0,
            'payments' => [['method' => 'cash', 'amount' => 50.0], ['method' => 'gcash', 'amount' => 50.0]],
        ],
    ]);

    expect($counts->cashOrders)->toBe(2)   // cash-only + split
        ->and($counts->gcashOrders)->toBe(3) // GCash-only + online + split
        ->and($counts->orders)->toBe(4);
});

it('counts nothing when there are no sales', function (): void {
    $counts = TenderCounts::fromSales([]);

    expect($counts->cashOrders)->toBe(0)
        ->and($counts->gcashOrders)->toBe(0)
        ->and($counts->orders)->toBe(0);
});
