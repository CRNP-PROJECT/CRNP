<?php

declare(strict_types=1);

use App\Stats\ServiceMix;

it('splits sales into dine-in and take-out by their service type', function (): void {
    $mix = ServiceMix::fromSales([
        ['serviceType' => 'dine_in', 'total' => 100.0],
        ['serviceType' => 'take_out', 'total' => 200.0],
        ['serviceType' => 'dine_in', 'total' => 50.0],
    ]);

    expect($mix->dineIn)->toBe(150.0)
        ->and($mix->takeOut)->toBe(200.0)
        ->and($mix->unspecified)->toBe(0.0)
        ->and($mix->dineInOrders)->toBe(2)
        ->and($mix->takeOutOrders)->toBe(1)
        ->and($mix->total())->toBe(350.0)
        ->and($mix->orders)->toBe(3);
});

it('buckets a sale with no service type as unspecified', function (): void {
    $mix = ServiceMix::fromSales([
        ['total' => 120.0],
        ['serviceType' => 'brunch', 'total' => 80.0],
    ]);

    expect($mix->unspecified)->toBe(200.0)
        ->and($mix->dineIn)->toBe(0.0)
        ->and($mix->takeOut)->toBe(0.0)
        ->and($mix->orders)->toBe(2);
});
