<?php

declare(strict_types=1);

namespace App\Orders\Actions;

use App\Orders\LineItem;
use App\Orders\Orders;
use App\Orders\OrderStatus;
use App\Rtdb\Contracts\RtdbClient;
use InvalidArgumentException;

/**
 * Removes a product line from a pending walk-in and recomputes the total.
 *
 * Removing the last line leaves a zero-total pending order, which settles
 * trivially; only a `pending` walk-in accepts the change.
 */
final readonly class RemoveItemFromWalkIn
{
    public function __construct(
        private RtdbClient $client,
        private Orders $orders,
    ) {}

    public function __invoke(string $orderId, string $productId): void
    {
        $order = $this->orders->require($orderId);

        if ($order->status !== OrderStatus::Pending) {
            throw new InvalidArgumentException('This order is already settled.');
        }

        $lines = array_values(array_filter(
            $order->lines,
            static fn(LineItem $line): bool => $line->productId !== $productId,
        ));

        $this->client->update("orders/{$orderId}", [
            'lines' => array_map(static fn(LineItem $line): array => $line->toNode(), $lines),
            'total' => LineItem::sum($lines),
        ]);
    }
}
