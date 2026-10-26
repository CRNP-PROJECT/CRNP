<?php

declare(strict_types=1);

use App\Stats\PaymentMix;

it('sums walk-in tender legs and treats an online pickup as GCash', function (): void {
    $mix = PaymentMix::fromSales([
        ['type' => 'walkin', 'total' => 200.0, 'payments' => [['method' => 'cash', 'amount' => 200.0]]],
        ['type' => 'walkin', 'total' => 150.0, 'payments' => [['method' => 'gcash', 'amount' => 150.0]]],
        ['type' => 'online', 'total' => 400.0, 'payments' => []],
        [
            'type' => 'walkin',
            'total' => 100.0,
            'payments' => [['method' => 'cash', 'amount' => 50.0], ['method' => 'gcash', 'amount' => 50.0]],
        ],
    ]);

    expect($mix->cash)->toBe(250.0)
        ->and($mix->gcash)->toBe(600.0)
        ->and($mix->total())->toBe(850.0)
        ->and($mix->orders)->toBe(4);
});

it('groups the same tender legs by the cashier who handled the sale', function (): void {
    $maria = ['uid' => 'staff-maria', 'name' => 'Maria Santos'];
    $jose = ['uid' => 'staff-jose', 'name' => 'Jose Cruz'];

    $rows = PaymentMix::byCashier([
        ['type' => 'walkin', 'total' => 200.0, 'handledBy' => $maria, 'payments' => [['method' => 'cash', 'amount' => 200.0]]],
        [
            'type' => 'walkin',
            'total' => 100.0,
            'handledBy' => $maria,
            'payments' => [['method' => 'cash', 'amount' => 50.0], ['method' => 'gcash', 'amount' => 50.0]],
        ],
        ['type' => 'online', 'total' => 400.0, 'handledBy' => $jose, 'payments' => []],
        ['type' => 'walkin', 'total' => 150.0, 'handledBy' => $jose, 'payments' => [['method' => 'gcash', 'amount' => 150.0]]],
    ]);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['name'])->toBe('Jose Cruz')
        ->and($rows[0]['cash'])->toBe(0.0)
        ->and($rows[0]['gcash'])->toBe(550.0)
        ->and($rows[0]['orders'])->toBe(2)
        ->and($rows[0]['total'])->toBe(550.0)
        ->and($rows[1]['name'])->toBe('Maria Santos')
        ->and($rows[1]['cash'])->toBe(250.0)
        ->and($rows[1]['gcash'])->toBe(50.0)
        ->and($rows[1]['orders'])->toBe(2)
        ->and($rows[1]['total'])->toBe(300.0);
});

it('leaves a backfilled sale without a cashier stamp out of the per-cashier rows', function (): void {
    $rows = PaymentMix::byCashier([
        ['type' => 'walkin', 'total' => 200.0, 'payments' => [['method' => 'cash', 'amount' => 200.0]]],
    ]);

    expect($rows)->toBe([]);
});

it('returns no per-cashier rows when there are no sales', function (): void {
    expect(PaymentMix::byCashier([]))->toBe([]);
});
