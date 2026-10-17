<?php

declare(strict_types=1);

use App\Orders\LineItem;

/**
 * The one line-summation rule the cart total and the walk-in ring-up total
 * share: integer centavos, so no floating-point drift can make two views of the
 * same order disagree by a centavo.
 */
it('sums a set of lines in centavos so no float drift creeps in', function (): void {
    $lines = [
        new LineItem('p1', 'Milktea', 33.33, 3),
        new LineItem('p2', 'Fries', 0.10, 1),
    ];

    expect(LineItem::sum($lines))->toBe(100.09);
});

it('returns zero for an empty set', function (): void {
    expect(LineItem::sum([]))->toBe(0.0);
});
