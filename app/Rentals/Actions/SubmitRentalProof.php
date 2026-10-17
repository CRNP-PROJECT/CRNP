<?php

declare(strict_types=1);

namespace App\Rentals\Actions;

use App\Cache\AvailabilityCache;
use App\Rentals\RentalBooking;
use App\Rentals\RentalProofNotAccepted;
use App\Rentals\RentalStatus;
use App\Rentals\RentalTracker;
use App\Rtdb\Contracts\RtdbClient;
use App\Support\Image\ImageCompressor;
use App\Support\Image\ImagePreset;
use InvalidArgumentException;

/**
 * Accepts a customer's GCash proof for a rental booking and auto-verifies it
 * (PLAN §4 *Rentals* — booking; the SAME gate as orders).
 *
 * The screenshot is re-encoded server-side to the proof preset and stored at the
 * sibling `proofs/rental-{id}` node — never on the booking node, so list and
 * tracker reads stay scalar. When the entered amount equals the booking total
 * **and** a ref# is present the booking **auto-verifies** (`confirmed`, the
 * units reserved); anything else flags a **flagged hold** (`verifying`, the
 * entered amount and ref#) for the counter's one-tap Confirm / Cancel, exactly
 * like an order.
 *
 * Only a booking still in a payment state accepts a proof, and the tracker's
 * coalesced entry is evicted so the verdict reaches the next poll. The proof
 * node carries no `sales` row — rentals are their own stream.
 */
final readonly class SubmitRentalProof
{
    public function __construct(
        private RtdbClient $client,
        private ImageCompressor $compressor,
        private RentalTracker $tracker,
        private AvailabilityCache $availability,
    ) {}

    public function __invoke(string $bookingId, float $amount, ?string $ref, string $image): void
    {
        $booking = $this->booking($bookingId);

        $ref = $ref !== null && trim($ref) !== '' ? trim($ref) : null;
        $amount = round($amount, 2);
        $receivedAt = now('UTC')->toIso8601ZuluString();

        $this->client->set("proofs/rental-{$bookingId}", [
            'bookingId' => $bookingId,
            'imageBase64' => $this->compressor->compressToBase64($image, ImagePreset::Proof),
            'amount' => $amount,
            'ref' => $ref,
            'uploadedAt' => $receivedAt,
        ]);

        $this->client->update("rentalBookings/{$bookingId}", $this->verdict($booking, $amount, $ref, $receivedAt));

        // A match confirms the booking (units reserved), so the catalog's
        // computed availability must reflect it on the next read.
        $this->availability->forgetRentals();
        $this->tracker->forget($bookingId);
    }

    /**
     * The verdict written to the booking node: a match confirms (units
     * reserved), anything else flags the hold. The image never rides here.
     *
     * @return array<string, mixed>
     */
    private function verdict(RentalBooking $booking, float $amount, ?string $ref, string $receivedAt): array
    {
        $common = [
            'proofInFlight' => false,
            'proofReceivedAt' => $receivedAt,
        ];

        if ($ref !== null && abs($amount - round($booking->total, 2)) < 0.005) {
            return [
                'status' => RentalStatus::Confirmed->value,
                'verifiedAt' => $receivedAt,
                'verification' => [
                    'verifiedAt' => $receivedAt,
                    'amount' => $amount,
                    'ref' => $ref,
                ],
                'payments' => [['method' => 'gcash', 'amount' => $amount]],
                'enteredAmount' => null,
                'gcashRef' => $ref,
                ...$common,
            ];
        }

        return [
            'status' => RentalStatus::Verifying->value,
            'enteredAmount' => $amount,
            'gcashRef' => $ref,
            'flaggedAt' => $receivedAt,
            ...$common,
        ];
    }

    private function booking(string $bookingId): RentalBooking
    {
        $node = $this->client->get("rentalBookings/{$bookingId}");
        $booking = is_array($node) ? RentalBooking::fromNode($bookingId, $node) : null;

        if ($booking === null) {
            throw new InvalidArgumentException("No rental booking with id [{$bookingId}].");
        }

        // Status-guarded: a proof is only accepted while the booking is still in
        // the payment state, so a stale upload cannot resurrect a settled one.
        if (! in_array($booking->status, [RentalStatus::AwaitingPayment, RentalStatus::Verifying], true)) {
            throw RentalProofNotAccepted::for($bookingId, $booking->status);
        }

        return $booking;
    }
}
