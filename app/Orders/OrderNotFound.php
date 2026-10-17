<?php

declare(strict_types=1);

namespace App\Orders;

use RuntimeException;

/**
 * Thrown when an order id resolves to no `orders/{id}` node.
 *
 * A missing order from a caller-supplied id is a runtime not-found, not a
 * programming error, so this is a {@see RuntimeException} the way the other
 * domain failures are ({@see \App\Proofs\ProofNotAccepted},
 * {@see \App\Rentals\RentalBlocked}). The message keeps the exact wording the
 * actions threw before, so nothing that reads it shifts.
 */
final class OrderNotFound extends RuntimeException
{
    public static function for(string $id): self
    {
        return new self("No order with id [{$id}].");
    }
}
