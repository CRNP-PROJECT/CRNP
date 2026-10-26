<?php

declare(strict_types=1);

namespace App\Orders\Actions;

use App\Orders\LineItem;
use App\Orders\Orders;
use App\Orders\OrderStatus;
use App\Rtdb\Contracts\RtdbClient;
use InvalidArgumentException;

/**
 * Removes one line from a pending walk-in and recomputes the total.
 *
 * The line is addressed by its position so two selections of the same product
 * (with different add-ons) can be removed independently. Removing the last
 * line leaves a zero-total pending order, which settles trivially; only a
 * `pending` walk-in accepts the change.
 */
final readonly class RemoveItemFromWalkIn
{
    public function __construct(
        private RtdbClient $client,
        private Orders $orders,
    ) {}

    public function __invoke(string $orderId, int $lineIndex): void
    {
        $order = $this->orders->require($orderId);

        if ($order->status !== OrderStatus::Pending) {
            throw new InvalidArgumentException('This order is already settled.');
        }

        if (! array_key_exists($lineIndex, $order->lines)) {
            throw new InvalidArgumentException('That line is no longer on the order.');
        }

        $lines = $order->lines;
        unset($lines[$lineIndex]);
        $lines = array_values($lines);

        $this->client->update("orders/{$orderId}", [
            'lines' => array_map(static fn(LineItem $line): array => $line->toNode(), $lines),
            'total' => LineItem::sum($lines),
        ]);
    }
}
