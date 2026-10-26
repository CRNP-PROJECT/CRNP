<?php

declare(strict_types=1);

namespace App\Http\Requests\Cashier;

use App\Orders\Order;
use App\Orders\Orders;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the walk-in settle POST: the one **Cash + GCash** form's two
 * amounts (PLAN §4 *Cashier POS*).
 *
 * - **Cash received** — what the customer handed over; it may be `0` for a
 *   GCash-only sale. It must cover the balance the GCash amount leaves.
 * - **GCash amount** — what the customer sent; it may be `0` for a cash-only
 *   sale and can never exceed the total.
 * - **GCash ref#** — required only when the GCash amount is above `0`.
 *
 * The order total is read here so a choice that cannot cover it is refused at
 * the boundary before any write. A stale or duplicate tap on a settled order
 * is short-circuited to no rules so the controller's status guard returns the
 * friendly no-op rather than a spurious error.
 */
final class SettleWalkInRequest extends FormRequest
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

        $total = $this->pendingOrder()?->total;

        return [
            'cash' => [
                'required',
                'numeric',
                'min:0',
                function (string $attribute, mixed $value, Closure $fail) use ($total): void {
                    if ($total === null || ! is_numeric($value)) {
                        return;
                    }

                    $gcash = $this->input('gcashAmount', 0);
                    $gcashCents = is_numeric($gcash) ? self::cents((float) $gcash) : 0;

                    if (self::cents((float) $value) + $gcashCents < self::cents($total)) {
                        $fail('The cash and GCash amounts must cover the total.');
                    }
                },
            ],
            'gcashAmount' => [
                'required',
                'numeric',
                'min:0',
                function (string $attribute, mixed $value, Closure $fail) use ($total): void {
                    if ($total === null || ! is_numeric($value)) {
                        return;
                    }

                    if (self::cents((float) $value) > self::cents($total)) {
                        $fail('The GCash amount cannot exceed the total.');
                    }
                },
            ],
            'gcashRef' => [
                Rule::requiredIf(fn(): bool => $this->gcashTyped() > 0),
                'nullable',
                'string',
                'max:64',
            ],
        ];
    }

    public function cash(): float
    {
        $cash = $this->validated()['cash'] ?? 0;

        return is_numeric($cash) ? (float) $cash : 0.0;
    }

    public function gcashAmount(): float
    {
        $amount = $this->validated()['gcashAmount'] ?? 0;

        return is_numeric($amount) ? (float) $amount : 0.0;
    }

    public function reference(): ?string
    {
        $ref = $this->validated()['gcashRef'] ?? null;

        return is_string($ref) ? $ref : null;
    }

    private function gcashTyped(): float
    {
        $amount = $this->input('gcashAmount');

        return is_numeric($amount) ? (float) $amount : 0.0;
    }

    private static function cents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    private function orderIsPending(): bool
    {
        return $this->pendingOrder() !== null;
    }

    private function pendingOrder(): ?Order
    {
        $walkin = $this->route('walkin');
        $order = is_string($walkin) ? app(Orders::class)->find($walkin) : null;

        return $order !== null && $order->isPending() ? $order : null;
    }
}
