<?php

declare(strict_types=1);

use App\Orders\Payment;
use App\Orders\PaymentMethod;
use App\Orders\SplitTender;
use App\Orders\TenderMethod;

it('treats cash at or above the total as all-cash with computed change', function (): void {
    $tender = SplitTender::for(TenderMethod::Cash, 250.00, 300.00, 0.00);

    expect($tender->payments)->toHaveCount(1)
        ->and($tender->payments[0]->method)->toBe(PaymentMethod::Cash)
        ->and($tender->payments[0]->amount)->toBe(250.0)
        ->and($tender->change)->toBe(50.0)
        ->and($tender->requiresGcashRef)->toBeFalse();
});

it('treats exact cash as all-cash with no change', function (): void {
    $tender = SplitTender::for(TenderMethod::Cash, 250.00, 250.00, 0.00);

    expect($tender->payments)->toHaveCount(1)
        ->and($tender->payments[0]->amount)->toBe(250.0)
        ->and($tender->change)->toBe(0.0)
        ->and($tender->requiresGcashRef)->toBeFalse();
});

it('refuses a cash choice that does not cover the total', function (): void {
    expect(fn(): mixed => SplitTender::for(TenderMethod::Cash, 250.00, 100.00, 0.00))
        ->toThrow(InvalidArgumentException::class);
});

it('treats a full GCash choice as one GCash leg that needs a ref', function (): void {
    $tender = SplitTender::for(TenderMethod::Gcash, 250.00, 0.00, 250.00);

    expect($tender->payments)->toHaveCount(1)
        ->and($tender->payments[0]->method)->toBe(PaymentMethod::Gcash)
        ->and($tender->payments[0]->amount)->toBe(250.0)
        ->and($tender->change)->toBe(0.0)
        ->and($tender->requiresGcashRef)->toBeTrue();
});

it('refuses a GCash choice whose typed amount is not the total', function (): void {
    expect(fn(): mixed => SplitTender::for(TenderMethod::Gcash, 250.00, 0.00, 200.00))
        ->toThrow(InvalidArgumentException::class);
});

it('splits the tender into the typed cash and GCash legs', function (): void {
    $tender = SplitTender::for(TenderMethod::Split, 250.00, 100.00, 150.00);

    expect($tender->payments)->toHaveCount(2)
        ->and($tender->payments[0]->method)->toBe(PaymentMethod::Cash)
        ->and($tender->payments[0]->amount)->toBe(100.0)
        ->and($tender->payments[1]->method)->toBe(PaymentMethod::Gcash)
        ->and($tender->payments[1]->amount)->toBe(150.0)
        ->and($tender->change)->toBe(0.0)
        ->and($tender->requiresGcashRef)->toBeTrue();
});

it('refuses a split whose amounts do not sum to the total', function (): void {
    expect(fn(): mixed => SplitTender::for(TenderMethod::Split, 250.00, 100.00, 100.00))
        ->toThrow(InvalidArgumentException::class);
});

it('refuses a split with a zero cash leg', function (): void {
    expect(fn(): mixed => SplitTender::for(TenderMethod::Split, 250.00, 0.00, 250.00))
        ->toThrow(InvalidArgumentException::class);
});

it('refuses a split with a zero GCash leg', function (): void {
    expect(fn(): mixed => SplitTender::for(TenderMethod::Split, 250.00, 250.00, 0.00))
        ->toThrow(InvalidArgumentException::class);
});

it('derives a clean cent split without float drift', function (): void {
    $tender = SplitTender::for(TenderMethod::Cash, 87.50, 100.00, 0.00);

    expect($tender->payments[0]->amount)->toBe(87.5)
        ->and($tender->change)->toBe(12.5);
});

it('always derives payments that sum to the total', function (): void {
    $cases = [
        [TenderMethod::Cash, 250.0, 300.0, 0.0],
        [TenderMethod::Cash, 250.0, 250.0, 0.0],
        [TenderMethod::Gcash, 250.0, 0.0, 250.0],
        [TenderMethod::Split, 250.0, 100.0, 150.0],
        [TenderMethod::Cash, 87.5, 100.0, 0.0],
    ];

    foreach ($cases as [$method, $total, $cash, $gcash]) {
        $tender = SplitTender::for($method, $total, $cash, $gcash);

        expect($tender->sum())->toBe($total);
    }
});

it('asserts a payment set sums to the total and throws otherwise', function (): void {
    SplitTender::assertBalanced([new Payment(PaymentMethod::Cash, 100.0), new Payment(PaymentMethod::Gcash, 150.0)], 250.0);

    expect(fn(): mixed => SplitTender::assertBalanced([new Payment(PaymentMethod::Cash, 100.0)], 250.0))
        ->toThrow(InvalidArgumentException::class);
});
