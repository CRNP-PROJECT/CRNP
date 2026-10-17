<?php

declare(strict_types=1);

namespace App\Orders\Actions;

use App\Catalog\Catalog;
use App\Catalog\Product;
use App\Orders\LineItem;
use App\Orders\Order;
use App\Orders\Orders;
use App\Orders\OrderStatus;
use App\Rtdb\Contracts\RtdbClient;
use InvalidArgumentException;

/**
 * Adds a product to a pending walk-in, or raises its quantity when it is
 * already on the order.
 *
 * The line snapshots the product's **selling price** — the promo price when a
 * promo is set — so the totals the cashier sees, the receipt prints, and the
 * kitchen ticket carries are all the same figure (PLAN §4 *Cashier POS* — promo
 * price and totals compute themselves). The order total is recomputed from the
 * lines on every write. Only a `pending` walk-in accepts tiles.
 */
final readonly class AddItemToWalkIn
{
    public function __construct(
        private RtdbClient $client,
        private Orders $orders,
        private Catalog $catalog,
    ) {}

    public function __invoke(string $orderId, string $productId, int $quantity = 1): void
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('A line must carry at least one item.');
        }

        $order = $this->pendingOrder($orderId);
        $product = $this->catalog->product($productId);

        if ($product === null) {
            throw new InvalidArgumentException("No product with id [{$productId}].");
        }

        $lines = $this->withLine($order->lines, $product, $quantity);

        $this->client->update("orders/{$orderId}", [
            'lines' => array_map(static fn(LineItem $line): array => $line->toNode(), $lines),
            'total' => LineItem::sum($lines),
        ]);
    }

    /**
     * @param  list<LineItem>  $lines
     * @return list<LineItem>
     */
    private function withLine(array $lines, Product $product, int $quantity): array
    {
        foreach ($lines as $index => $line) {
            if ($line->productId === $product->id) {
                $lines[$index] = new LineItem(
                    $line->productId,
                    $line->name,
                    $line->unitPrice,
                    $line->quantity + $quantity,
                    $line->addOns,
                );

                return $lines;
            }
        }

        $lines[] = new LineItem(
            productId: $product->id,
            name: $product->name,
            unitPrice: $product->sellingPrice(),
            quantity: $quantity,
            addOns: $product->addOns,
        );

        return $lines;
    }

    private function pendingOrder(string $orderId): Order
    {
        $order = $this->orders->require($orderId);

        if ($order->status !== OrderStatus::Pending) {
            throw new InvalidArgumentException('This order is already settled.');
        }

        return $order;
    }
}
