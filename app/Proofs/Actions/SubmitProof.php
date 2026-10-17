<?php

declare(strict_types=1);

namespace App\Proofs\Actions;

use App\Cache\KitchenBoardCache;
use App\Orders\Order;
use App\Orders\Orders;
use App\Orders\OrderStatus;
use App\Orders\OrderTracker;
use App\Proofs\ProofNotAccepted;
use App\Rtdb\Contracts\RtdbClient;
use App\Support\Image\ImageCompressor;
use App\Support\Image\ImagePreset;

/**
 * Accepts a customer's GCash proof of payment and auto-verifies it (PLAN §4
 * *Customer site* — proof of payment).
 *
 * The screenshot is re-encoded server-side to the proof preset (adaptive JPEG
 * ≤300 KB, ≥720 px floor) and stored at the sibling `proofs/{id}` node — never
 * on the order node, so list/queue/tracker/board reads stay scalar. The order
 * node then carries only the verdict: when the entered amount equals the order
 * total **and** a ref# is present it **auto-verifies** (`cooking`, a
 * `verification` record) so the kitchen ticket appears the same instant;
 * anything else lands a **flagged hold** (`verifying`, the entered amount and
 * ref#) for the cashier's one-tap Approve/Reject (#27).
 *
 * Only an order still in a payment state accepts a proof, and the tracker's
 * coalesced entry is evicted so the verdict reaches the customer's next poll
 * without waiting out the cadence. A match puts the ticket on the board the
 * same instant, so the kitchen board's coalesced entry is evicted too.
 */
final readonly class SubmitProof
{
    public function __construct(
        private RtdbClient $client,
        private Orders $orders,
        private ImageCompressor $compressor,
        private OrderTracker $tracker,
        private KitchenBoardCache $board,
    ) {}

    public function __invoke(string $orderId, float $amount, ?string $ref, string $image): void
    {
        $order = $this->order($orderId);

        $ref = $ref !== null && trim($ref) !== '' ? trim($ref) : null;
        $amount = round($amount, 2);
        $receivedAt = now('UTC')->toIso8601ZuluString();

        $this->client->set("proofs/{$orderId}", [
            'orderId' => $orderId,
            'imageBase64' => $this->compressor->compressToBase64($image, ImagePreset::Proof),
            'amount' => $amount,
            'ref' => $ref,
            'uploadedAt' => $receivedAt,
        ]);

        $this->client->update("orders/{$orderId}", $this->verdict($order, $amount, $ref, $receivedAt));

        $this->tracker->forget($orderId);
        $this->board->forget();
    }

    /**
     * The verdict written to the order node: a match verifies, anything else
     * flags the hold. The proof image itself never rides here.
     *
     * @return array<string, mixed>
     */
    private function verdict(Order $order, float $amount, ?string $ref, string $receivedAt): array
    {
        $common = [
            'proofInFlight' => false,
            'proofReceivedAt' => $receivedAt,
        ];

        if ($ref !== null && abs($amount - round($order->total, 2)) < 0.005) {
            return [
                'status' => OrderStatus::Cooking->value,
                'verifiedAt' => $receivedAt,
                'verification' => [
                    'verifiedAt' => $receivedAt,
                    'amount' => $amount,
                    'ref' => $ref,
                ],
                'enteredAmount' => null,
                'gcashRef' => $ref,
                ...$common,
            ];
        }

        return [
            'status' => OrderStatus::Verifying->value,
            'enteredAmount' => $amount,
            'gcashRef' => $ref,
            'flaggedAt' => $receivedAt,
            ...$common,
        ];
    }

    private function order(string $orderId): Order
    {
        $order = $this->orders->require($orderId);

        // Status-guarded: a proof is only accepted while the order is still in a
        // payment state, so a stale upload cannot resurrect a settled order.
        if (! in_array($order->status, [OrderStatus::AwaitingPayment, OrderStatus::Verifying], true)) {
            throw ProofNotAccepted::for($orderId, $order->status);
        }

        return $order;
    }
}
