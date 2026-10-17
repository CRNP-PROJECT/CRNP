<?php

declare(strict_types=1);

use App\Orders\Payment;
use App\Orders\PaymentMethod;
use App\Orders\SplitTender;

it('treats cash at or above the total as all-cash with computed change', function (): void {
    $tender = SplitTender::for(250.00, 300.00);

    expect($tender->payments)->toHaveCount(1)
        ->and($tender->payments[0]->method)->toBe(PaymentMethod::Cash)
        ->and($tender->payments[0]->amount)->toBe(250.0)
        ->and($tender->change)->toBe(50.0)
        ->and($tender->requiresGcashRef)->toBeFalse();
});

it('treats exact cash as all-cash with no change', function (): void {
    $tender = SplitTender::for(250.00, 250.00);

    expect($tender->payments)->toHaveCount(1)
        ->and($tender->payments[0]->amount)->toBe(250.0)
        ->and($tender->change)->toBe(0.0)
        ->and($tender->requiresGcashRef)->toBeFalse();
});

it('splits the tender when cash is under the total', function (): void {
    $tender = SplitTender::for(250.00, 100.00);

    expect($tender->payments)->toHaveCount(2)
        ->and($tender->payments[0]->method)->toBe(PaymentMethod::Cash)
        ->and($tender->payments[0]->amount)->toBe(100.0)
        ->and($tender->payments[1]->method)->toBe(PaymentMethod::Gcash)
        ->and($tender->payments[1]->amount)->toBe(150.0)
        ->and($tender->change)->toBe(0.0)
        ->and($tender->requiresGcashRef)->toBeTrue();
});

it('derives a clean cent split without float drift', function (): void {
    $tender = SplitTender::for(87.50, 100.00);

    expect($tender->payments[0]->amount)->toBe(87.5)
        ->and($tender->change)->toBe(12.5);
});

it('treats a zero cash tender as an all-GCash leg that needs a ref', function (): void {
    $tender = SplitTender::for(100.00, 0.00);

    expect($tender->payments)->toHaveCount(1)
        ->and($tender->payments[0]->method)->toBe(PaymentMethod::Gcash)
        ->and($tender->payments[0]->amount)->toBe(100.0)
        ->and($tender->change)->toBe(0.0)
        ->and($tender->requiresGcashRef)->toBeTrue();
});

it('always derives payments that sum to the total', function (): void {
    foreach ([[250.0, 300.0], [250.0, 250.0], [250.0, 100.0], [87.5, 100.0], [100.0, 0.0]] as [$total, $cash]) {
        $tender = SplitTender::for($total, $cash);

        expect($tender->sum())->toBe($total);
    }
});

it('asserts a payment set sums to the total and throws otherwise', function (): void {
    SplitTender::assertBalanced([new Payment(PaymentMethod::Cash, 100.0), new Payment(PaymentMethod::Gcash, 150.0)], 250.0);

    expect(fn(): mixed => SplitTender::assertBalanced([new Payment(PaymentMethod::Cash, 100.0)], 250.0))
        ->toThrow(InvalidArgumentException::class);
});
