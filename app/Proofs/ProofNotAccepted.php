<?php

declare(strict_types=1);

namespace App\Proofs;

use App\Orders\OrderStatus;
use RuntimeException;

/**
 * Thrown when a proof is uploaded against an order that has already left the
 * payment states (PLAN §4 *Customer site* — proof of payment).
 *
 * Only an `awaiting_payment` or `verifying` order accepts a screenshot, so a
 * stale upload cannot resurrect a settled order. The controller maps this to a
 * 403 rather than a 500.
 */
final class ProofNotAccepted extends RuntimeException
{
    public static function for(string $orderId, OrderStatus $status): self
    {
        return new self(
            "The order [{$orderId}] in state [{$status->value}] does not accept a proof.",
        );
    }
}
