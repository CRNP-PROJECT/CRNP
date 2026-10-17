<?php

declare(strict_types=1);

namespace App\Proofs\Actions;

use App\Orders\Orders;
use App\Orders\OrderStatus;
use App\Orders\OrderTracker;
use App\Proofs\ProofNotAccepted;
use App\Rtdb\Contracts\RtdbClient;

/**
 * Records that a proof upload has begun (PLAN §4 *Customer site* — proof of
 * payment; *Expiry*).
 *
 * A request sent with no terminal response counts as **in flight**: the client
 * persists `proofInFlight` / `uploadStartedAt` the moment the picker selection
 * starts uploading, so the expiry sweep (#25) holds the order across 15:00 —
 * a customer is never dismissed for a connection they could not see failing.
 * {@see SubmitProof} clears the marker when the screenshot lands.
 *
 * Only an order still in a payment state can begin an upload, and the tracker's
 * coalesced entry is evicted so the marker is visible on the next poll.
 */
final readonly class StartProofUpload
{
    public function __construct(
        private RtdbClient $client,
        private Orders $orders,
        private OrderTracker $tracker,
    ) {}

    public function __invoke(string $orderId): void
    {
        $order = $this->orders->require($orderId);

        if (! in_array($order->status, [OrderStatus::AwaitingPayment, OrderStatus::Verifying], true)) {
            throw ProofNotAccepted::for($orderId, $order->status);
        }

        $this->client->update("orders/{$orderId}", [
            'proofInFlight' => true,
            'uploadStartedAt' => now('UTC')->toIso8601ZuluString(),
        ]);

        $this->tracker->forget($orderId);
    }
}
