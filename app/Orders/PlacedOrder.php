<?php

declare(strict_types=1);

namespace App\Orders;

/**
 * The result of a place-order attempt: the order (fresh or replayed) and
 * whether the client's idempotency key had already been used.
 *
 * A replay returns the **original** order so the controller can send the
 * customer straight to its tracker without opening a second payment window.
 */
final readonly class PlacedOrder
{
    public function __construct(
        public Order $order,
        public bool $replayed,
    ) {}
}
