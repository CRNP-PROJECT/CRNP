<?php

declare(strict_types=1);

namespace App\Http\Requests\Cashier;

use App\Orders\Orders;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the walk-in add-item tile tap: the product and quantity (PLAN §4
 * *Cashier POS*).
 *
 * A tile tap posts the product id and a quantity; the promo price and line
 * total compute server-side in the action, never from the client. A stale tap
 * on a settled order is short-circuited to no rules so the controller's status
 * guard returns the friendly no-op rather than a spurious validation error.
 */
final class AddWalkInItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        if (! $this->orderIsPending()) {
            return [];
        }

        return [
            'product' => ['required', 'string'],
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }

    public function productId(): string
    {
        /** @var string $product */
        $product = $this->validated()['product'];

        return $product;
    }

    public function quantity(): int
    {
        /** @var int|string $quantity */
        $quantity = $this->validated()['quantity'];

        return (int) $quantity;
    }

    private function orderIsPending(): bool
    {
        $walkin = $this->route('walkin');

        return is_string($walkin) && app(Orders::class)->find($walkin)?->isPending() === true;
    }
}
