<?php

declare(strict_types=1);

namespace App\Orders;

use App\Rtdb\Contracts\RtdbClient;
use App\Rtdb\RtdbQuery;

/**
 * Reads orders from `orders/{id}` (PLAN §4 *Data layer*).
 *
 * The status-equal read seam: {@see byStatus()} turns an indexed query into
 * parsed orders, so a caller that wants "the orders in this state" never
 * re-issues the query or re-parses the rows. The kitchen board mirrors the
 * `cooking` status through it, and a walk-in and an online order share the
 * rule.
 */
final readonly class Orders
{
    public function __construct(private RtdbClient $client) {}

    public function find(string $id): ?Order
    {
        $node = $this->client->get("orders/{$id}");

        return is_array($node) ? Order::fromNode($id, $node) : null;
    }

    /**
     * The order at $id, or a thrown {@see OrderNotFound}.
     *
     * The single load-and-parse seam every action routes through: {@see find()}'s
     * nullable read, promoted to a failure when a caller supplies an id that must
     * exist. A controller's 404 stays its own concern — this throw is for the
     * action layer.
     */
    public function require(string $id): Order
    {
        return $this->find($id) ?? throw OrderNotFound::for($id);
    }

    /**
     * The orders in one status, keyed by id (PLAN §4 *Data layer*).
     *
     * The single status-equal read seam: every reader that wants "the orders in
     * this state" routes through here rather than re-issuing the query and
     * re-parsing the rows. A row that cannot be parsed is skipped, so a caller
     * never sees a half-order. The sibling reads ({@see \App\Rentals\RentalBookings::byStatus()},
     * {@see \App\Reservations\Reservations::forDate()}) share this shape.
     *
     * @return array<string, Order>
     */
    public function byStatus(OrderStatus $status): array
    {
        $rows = $this->client->query(
            'orders',
            RtdbQuery::orderBy('status')->equalTo($status->value),
        );

        $orders = [];

        foreach ($rows as $id => $row) {
            $order = Order::fromNode((string) $id, $row);

            if ($order !== null) {
                $orders[(string) $id] = $order;
            }
        }

        return $orders;
    }

    /**
     * The account's active order, from `users/{uid}/activeOrderId` (#23).
     *
     * The tracker reopens this with zero typing; a terminal order (served,
     * dismissed, rejected) is not active, so the customer falls back to the menu.
     */
    public function activeFor(string $uid): ?Order
    {
        $activeId = $this->client->get("users/{$uid}/activeOrderId");

        if (! is_string($activeId) || $activeId === '') {
            return null;
        }

        $order = $this->find($activeId);

        return $order !== null && $order->isActive() ? $order : null;
    }
}
