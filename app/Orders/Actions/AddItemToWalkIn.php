<?php

declare(strict_types=1);

namespace App\Orders\Actions;

use App\Catalog\AddOn;
use App\Catalog\Catalog;
use App\Catalog\Product;
use App\Orders\LineItem;
use App\Orders\Order;
use App\Orders\Orders;
use App\Orders\OrderStatus;
use App\Rtdb\Contracts\RtdbClient;
use InvalidArgumentException;

/**
 * Adds a product to a pending walk-in, or raises its quantity when the same
 * product with the same add-ons is already on the order.
 *
 * The line snapshots the product's **selling price** — the promo price when a
 * promo is set — and only the **ticked add-ons** the product offers. Their
 * prices are added per unit by {@see LineItem::lineTotal()}, so a ticked add-on
 * raises the line total and nothing else. Two selections of the same product
 * (with and without an add-on) stay separate lines. The order total is
 * recomputed from the lines on every write. Only a `pending` walk-in accepts
 * tiles.
 */
final readonly class AddItemToWalkIn
{
    public function __construct(
        private RtdbClient $client,
        private Orders $orders,
        private Catalog $catalog,
    ) {}

    /**
     * @param  list<string>  $addOnNames  the add-ons the cashier ticked
     */
    public function __invoke(string $orderId, string $productId, int $quantity = 1, array $addOnNames = []): void
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('A line must carry at least one item.');
        }

        $order = $this->pendingOrder($orderId);
        $product = $this->catalog->product($productId);

        if ($product === null) {
            throw new InvalidArgumentException("No product with id [{$productId}].");
        }

        $addOns = $this->offeredAddOns($product, $addOnNames);
        $lines = $this->withLine($order->lines, $product, $quantity, $addOns);

        $this->client->update("orders/{$orderId}", [
            'lines' => array_map(static fn(LineItem $line): array => $line->toNode(), $lines),
            'total' => LineItem::sum($lines),
        ]);
    }

    /**
     * @param  list<LineItem>  $lines
     * @param  list<AddOn>  $addOns
     * @return list<LineItem>
     */
    private function withLine(array $lines, Product $product, int $quantity, array $addOns): array
    {
        foreach ($lines as $index => $line) {
            if ($line->productId === $product->id && $this->sameAddOns($line->addOns, $addOns)) {
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
            addOns: $addOns,
        );

        return $lines;
    }

    /**
     * The ticked add-ons the product actually offers, in menu order. A stale or
     * invented name is dropped rather than priced.
     *
     * @param  list<string>  $chosen
     * @return list<AddOn>
     */
    private function offeredAddOns(Product $product, array $chosen): array
    {
        $addOns = [];

        foreach ($product->addOns as $addOn) {
            if (in_array($addOn->name, $chosen, true)) {
                $addOns[] = $addOn;
            }
        }

        return $addOns;
    }

    /**
     * @param  list<AddOn>  $a
     * @param  list<AddOn>  $b
     */
    private function sameAddOns(array $a, array $b): bool
    {
        if (count($a) !== count($b)) {
            return false;
        }

        foreach ($a as $index => $addOn) {
            if ($addOn->name !== $b[$index]->name || $addOn->price !== $b[$index]->price) {
                return false;
            }
        }

        return true;
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
