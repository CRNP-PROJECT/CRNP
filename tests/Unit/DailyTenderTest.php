<?php

declare(strict_types=1);

use App\Stats\DailyTender;

it('buckets orders and bookings into one row per day, split by GCash and cash', function (): void {
    $table = DailyTender::fromSalesAndBookings(
        [
            // Walk-in split: ₱25 cash + ₱175 GCash on the 6th.
            [
                'type' => 'walkin',
                'total' => 200.0,
                'soldAt' => '2026-10-06T02:00:00Z',
                'payments' => [
                    ['method' => 'cash', 'amount' => 25.0],
                    ['method' => 'gcash', 'amount' => 175.0],
                ],
            ],
            // Online pickup: GCash in full on the 6th.
            ['type' => 'online', 'total' => 400.0, 'soldAt' => '2026-10-06T05:00:00Z', 'payments' => []],
            // All-cash walk-in on the 7th.
            [
                'type' => 'walkin',
                'total' => 60.0,
                'soldAt' => '2026-10-07T02:00:00Z',
                'payments' => [['method' => 'cash', 'amount' => 60.0]],
            ],
        ],
        [
            // Paid booking, no legs: GCash in full on the 8th.
            ['status' => 'confirmed', 'dateFrom' => '2026-10-08', 'total' => 150.0, 'payments' => []],
            // Split booking on the 8th too: ₱50 cash + ₱50 GCash.
            [
                'status' => 'handed_over',
                'dateFrom' => '2026-10-08',
                'total' => 100.0,
                'payments' => [
                    ['method' => 'cash', 'amount' => 50.0],
                    ['method' => 'gcash', 'amount' => 50.0],
                ],
            ],
            // Unpaid window must never count.
            ['status' => 'awaiting_payment', 'dateFrom' => '2026-10-09', 'total' => 999.0, 'payments' => []],
        ],
    );

    expect($table->days)->toHaveCount(3)
        ->and($table->days[0])->toBe([
            'date' => '2026-10-06',
            'ordersCash' => 25.0,
            'ordersGcash' => 575.0,
            'bookingsCash' => 0.0,
            'bookingsGcash' => 0.0,
        ])
        ->and($table->days[1])->toBe([
            'date' => '2026-10-07',
            'ordersCash' => 60.0,
            'ordersGcash' => 0.0,
            'bookingsCash' => 0.0,
            'bookingsGcash' => 0.0,
        ])
        ->and($table->days[2])->toBe([
            'date' => '2026-10-08',
            'ordersCash' => 0.0,
            'ordersGcash' => 0.0,
            'bookingsCash' => 50.0,
            'bookingsGcash' => 200.0,
        ]);

    expect($table->totals())->toBe([
        'ordersCash' => 85.0,
        'ordersGcash' => 575.0,
        'bookingsCash' => 50.0,
        'bookingsGcash' => 200.0,
        'total' => 910.0,
    ]);
});

it('files an order under its Manila business day, not the UTC day', function (): void {
    // 2026-10-06T22:00:00Z is 2026-10-07 06:00 in Manila, so it files on the 7th.
    $table = DailyTender::fromSalesAndBookings(
        [
            [
                'type' => 'walkin',
                'total' => 100.0,
                'soldAt' => '2026-10-06T22:00:00Z',
                'payments' => [['method' => 'cash', 'amount' => 100.0]],
            ],
        ],
        [],
    );

    expect($table->days)->toHaveCount(1)
        ->and($table->days[0]['date'])->toBe('2026-10-07');
});

it('returns no rows when there is nothing to collect', function (): void {
    $table = DailyTender::fromSalesAndBookings([], []);

    expect($table->days)->toBe([])
        ->and($table->totals()['total'])->toBe(0.0);
});
