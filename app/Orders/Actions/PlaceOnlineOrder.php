<?php

declare(strict_types=1);

namespace App\Orders\Actions;

use App\Auth\RtdbUser;
use App\Orders\Cart;
use App\Orders\LineItem;
use App\Orders\Order;
use App\Orders\OrderCode;
use App\Orders\Orders;
use App\Orders\OrderStatus;
use App\Orders\OrderType;
use App\Orders\PaymentWindow;
use App\Orders\PickupTime;
use App\Orders\PlacedOrder;
use App\Rtdb\Contracts\RtdbClient;
use Illuminate\Support\Str;

/**
 * Places an online pickup order: mints the order code, persists the order
 * (`awaiting_payment`), records the idempotency key, and starts the 15-minute
 * payment window (PLAN §4 *Customer site* — place order).
 *
 * The **idempotency key** is minted per checkout attempt by the client. A
 * replay of the same key returns the **original** order — so a timeout-then-retry
 * or a refresh cannot open a second 15-minute window — and no second order is
 * written. The key is recorded at `idem/{key} → orderId`, and the order is bound
 * to the customer at `users/{uid}/activeOrderId` so the tracker reopens with zero
 * typing.
 *
 * The order is pickup-only and GCash-only (no table field), carries the frozen
 * line prices, and sets `deadlineAt = placedAt + 15 min` in one place shared
 * with the expiry sweep (#25).
 */
final readonly class PlaceOnlineOrder
{
    public function __construct(
        private RtdbClient $client,
        private Orders $orders,
    ) {}

    /**
     * Resolve an existing order for a replayed key, or null when the key is new.
     */
    public function replay(string $idempotencyKey): ?Order
    {
        $node = $this->client->get('idem/' . $idempotencyKey);

        if (! is_array($node)) {
            return null;
        }

        $orderId = $node['orderId'] ?? null;

        if (! is_string($orderId) || $orderId === '') {
            return null;
        }

        return $this->orders->find($orderId);
    }

    public function __invoke(
        RtdbUser $customer,
        Cart $cart,
        PickupTime $pickup,
        string $idempotencyKey,
    ): PlacedOrder {
        $idempotencyKey = trim($idempotencyKey);

        // Idempotency wins: the same client key returns the original order and
        // opens no second window.
        $existing = $this->replay($idempotencyKey);

        if ($existing !== null) {
            return new PlacedOrder($existing, true);
        }

        $placedAt = now('UTC');
        $id = (string) Str::ulid();

        $this->client->set("orders/{$id}", [
            'code' => OrderCode::draw()->value,
            'type' => OrderType::Online->value,
            'status' => OrderStatus::AwaitingPayment->value,
            'lines' => array_map(static fn(LineItem $line): array => $line->toNode(), $cart->lines()),
            'total' => $cart->total(),
            'payments' => [],
            'change' => 0.0,
            'customerUid' => $customer->sub,
            'placedAt' => $placedAt->toIso8601ZuluString(),
            'pickupAt' => $pickup->toNode(),
            'deadlineAt' => PaymentWindow::deadlineFrom($placedAt)->toIso8601ZuluString(),
        ]);

        // `idem/{key} → orderId` is the replay record; `users/{uid}` binds the
        // active order so the tracker reopens with zero typing.
        $this->client->set("idem/{$idempotencyKey}", ['orderId' => $id]);
        $this->client->update("users/{$customer->sub}", ['activeOrderId' => $id]);

        $node = $this->client->get("orders/{$id}");

        return new PlacedOrder(
            is_array($node) ? Order::fromNode($id, $node) ?? $this->missing($id) : $this->missing($id),
            false,
        );
    }

    private function missing(string $id): never
    {
        throw new \RuntimeException("The placed order [{$id}] could not be read back.");
    }
}
