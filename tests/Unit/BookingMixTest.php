<?php

declare(strict_types=1);

use App\Stats\BookingMix;

it('counts a confirmed booking with no tender legs as GCash in full', function (): void {
    $mix = BookingMix::fromBookings([
        ['status' => 'confirmed', 'total' => 150.0, 'payments' => []],
    ]);

    expect($mix->gcash)->toBe(150.0)
        ->and($mix->cash)->toBe(0.0)
        ->and($mix->total())->toBe(150.0)
        ->and($mix->bookings)->toBe(1);
});

it('sums each tender leg of a split booking by its method', function (): void {
    $mix = BookingMix::fromBookings([
        [
            'status' => 'handed_over',
            'total' => 200.0,
            'payments' => [
                ['method' => 'cash', 'amount' => 50.0],
                ['method' => 'gcash', 'amount' => 150.0],
            ],
        ],
    ]);

    expect($mix->cash)->toBe(50.0)
        ->and($mix->gcash)->toBe(150.0)
        ->and($mix->bookings)->toBe(1);
});

it('ignores a booking that was never paid for', function (): void {
    $mix = BookingMix::fromBookings([
        ['status' => 'awaiting_payment', 'total' => 150.0, 'payments' => []],
        ['status' => 'verifying', 'total' => 150.0, 'payments' => []],
        ['status' => 'lapsed', 'total' => 150.0, 'payments' => []],
        ['status' => 'cancelled', 'total' => 150.0, 'payments' => []],
    ]);

    expect($mix->total())->toBe(0.0)
        ->and($mix->bookings)->toBe(0);
});
