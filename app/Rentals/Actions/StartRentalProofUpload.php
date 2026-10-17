<?php

declare(strict_types=1);

namespace App\Rentals\Actions;

use App\Rentals\RentalBooking;
use App\Rentals\RentalProofNotAccepted;
use App\Rentals\RentalStatus;
use App\Rentals\RentalTracker;
use App\Rtdb\Contracts\RtdbClient;
use InvalidArgumentException;

/**
 * Records that a rental proof upload has begun (PLAN §4 *Rentals*).
 *
 * A request sent with no terminal response counts as **in flight**: the client
 * persists `proofInFlight` the moment the picker selection starts uploading, so
 * the rental expiry sweep holds the booking across its deadline — a customer is
 * never lapsed for a connection they could not see failing. {@see
 * SubmitRentalProof} clears the marker when the screenshot lands.
 */
final readonly class StartRentalProofUpload
{
    public function __construct(
        private RtdbClient $client,
        private RentalTracker $tracker,
    ) {}

    public function __invoke(string $bookingId): void
    {
        $node = $this->client->get("rentalBookings/{$bookingId}");
        $booking = is_array($node) ? RentalBooking::fromNode($bookingId, $node) : null;

        if ($booking === null) {
            throw new InvalidArgumentException("No rental booking with id [{$bookingId}].");
        }

        if (! in_array($booking->status, [RentalStatus::AwaitingPayment, RentalStatus::Verifying], true)) {
            throw RentalProofNotAccepted::for($bookingId, $booking->status);
        }

        $this->client->update("rentalBookings/{$bookingId}", [
            'proofInFlight' => true,
            'uploadStartedAt' => now('UTC')->toIso8601ZuluString(),
        ]);

        $this->tracker->forget($bookingId);
    }
}
