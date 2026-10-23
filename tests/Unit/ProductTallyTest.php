<?php

declare(strict_types=1);

use App\Stats\ProductTally;

it('ranks products and splits each one’s units across the days', function (): void {
    $sales = [
        [
            'soldAt' => '2026-10-05T02:00:00Z',
            'lines' => [
                ['productId' => 'p1', 'name' => 'Adobo', 'unitPrice' => 100.0, 'quantity' => 2],
                ['productId' => 'p2', 'name' => 'Milktea', 'unitPrice' => 50.0, 'quantity' => 1],
            ],
        ],
        [
            'soldAt' => '2026-10-06T02:00:00Z',
            'lines' => [
                ['productId' => 'p1', 'name' => 'Adobo', 'unitPrice' => 100.0, 'quantity' => 3],
            ],
        ],
    ];

    $ranked = ProductTally::rankByDay($sales, ['2026-10-05', '2026-10-06'], 5);

    expect($ranked)->toHaveCount(2)
        ->and($ranked[0]['productId'])->toBe('p1')
        ->and($ranked[0]['units'])->toBe(5)
        ->and($ranked[0]['revenue'])->toBe(500.0)
        ->and($ranked[0]['days'])->toBe([2, 3])
        ->and($ranked[1]['productId'])->toBe('p2')
        ->and($ranked[1]['days'])->toBe([1, 0]);
});

it('counts a sale without a readable soldAt toward the total but no day', function (): void {
    $ranked = ProductTally::rankByDay([
        [
            'lines' => [['productId' => 'p1', 'name' => 'Adobo', 'unitPrice' => 100.0, 'quantity' => 4]],
        ],
    ], ['2026-10-05', '2026-10-06'], 5);

    expect($ranked[0]['units'])->toBe(4)
        ->and($ranked[0]['days'])->toBe([0, 0]);
});

it('caps the ranked list at the limit', function (): void {
    $ranked = ProductTally::rankByDay([
        [
            'soldAt' => '2026-10-05T02:00:00Z',
            'lines' => [
                ['productId' => 'p1', 'name' => 'Adobo', 'unitPrice' => 100.0, 'quantity' => 2],
                ['productId' => 'p2', 'name' => 'Milktea', 'unitPrice' => 50.0, 'quantity' => 1],
            ],
        ],
    ], ['2026-10-05'], 1);

    expect($ranked)->toHaveCount(1)
        ->and($ranked[0]['productId'])->toBe('p1');
});
