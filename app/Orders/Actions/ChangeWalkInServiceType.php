<?php

declare(strict_types=1);

namespace App\Orders\Actions;

use App\Orders\Orders;
use App\Orders\OrderStatus;
use App\Orders\ServiceType;
use App\Rtdb\Contracts\RtdbClient;
use InvalidArgumentException;

/**
 * Switches a pending walk-in between dine-in and take-out without touching the
 * cart (PLAN §4 *Cashier POS*).
 *
 * The service type belongs to the order, not to the ring-up form: a customer
 * who changes their mind at the counter keeps their lines and total. Only a
 * `pending` walk-in accepts the change; the settle then snapshots the latest
 * value onto `sales/{id}` for the sales report.
 */
final readonly class ChangeWalkInServiceType
{
    public function __construct(
        private RtdbClient $client,
        private Orders $orders,
    ) {}

    public function __invoke(string $orderId, ServiceType $serviceType): void
    {
        $order = $this->orders->require($orderId);

        if ($order->status !== OrderStatus::Pending) {
            throw new InvalidArgumentException('This order is already settled.');
        }

        $this->client->update("orders/{$orderId}", [
            'serviceType' => $serviceType->value,
        ]);
    }
}
