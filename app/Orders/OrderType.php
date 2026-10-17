<?php

declare(strict_types=1);

namespace App\Orders;

/**
 * The kind of order an `orders/{id}` row is.
 *
 * A **walk-in** is rung up at the POS by a cashier and settles at the counter;
 * an **online** pickup order is placed on the public site and settles by the
 * cashier's Mark served (#23 / #26). Both draw from the one order-code space.
 */
enum OrderType: string
{
    case WalkIn = 'walkin';
    case Online = 'online';
}
