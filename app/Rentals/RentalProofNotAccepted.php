<?php

declare(strict_types=1);

namespace App\Rentals;

use RuntimeException;

/**
 * Thrown when a proof is uploaded against a rental booking that has already left
 * the payment state (PLAN §4 *Rentals* — booking).
 *
 * Only an `awaiting_payment` booking accepts a screenshot, so a stale upload
 * cannot resurrect a settled booking. The controller maps this to a 403 rather
 * than a 500. It mirrors the order stream's {@see \App\Proofs\ProofNotAccepted}
 * but stays in the rental namespace, so the two streams share no status type.
 */
final class RentalProofNotAccepted extends RuntimeException
{
    public static function for(string $bookingId, RentalStatus $status): self
    {
        return new self(
            "The rental booking [{$bookingId}] in state [{$status->value}] does not accept a proof.",
        );
    }
}
