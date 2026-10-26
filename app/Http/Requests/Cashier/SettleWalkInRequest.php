<?php

declare(strict_types=1);

namespace App\Http\Requests\Cashier;

use App\Orders\Order;
use App\Orders\Orders;
use App\Orders\TenderMethod;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the walk-in settle POST: the chosen payment method and the fields
 * that choice needs (PLAN §4 *Cashier POS*).
 *
 * - **Cash** — the cash handed over, which must cover the total; change is
 *   computed.
 * - **GCash** — the GCash amount (which must equal the total) plus a ref#.
 * - **Cash + GCash** — the cash and GCash amounts, which must sum to the
 *   total, plus the GCash ref#.
 *
 * The order total is read here so a choice that cannot balance is refused at
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

        $rules = ['method' => ['required', Rule::enum(TenderMethod::class)]];
        $methodInput = $this->input('method');
        $method = is_string($methodInput) ? TenderMethod::tryFrom($methodInput) : null;

        if ($method === null) {
            return $rules;
        }

        $total = $this->pendingOrder()?->total;

        if ($method->needsCash()) {
            $rules['cash'] = [
                'required',
                'numeric',
                'min:0',
                function (string $attribute, mixed $value, Closure $fail) use ($method, $total): void {
                    if ($total === null || ! is_numeric($value)) {
                        return;
                    }

                    $cashCents = self::cents((float) $value);

                    if ($method === TenderMethod::Cash && $cashCents < self::cents($total)) {
                        $fail('Cash received must cover the total.');
                    }

                    if ($method === TenderMethod::Split && $cashCents <= 0) {
                        $fail('A split needs a cash amount above zero.');
                    }
                },
            ];
        }

        if ($method->needsGcashAmount()) {
            $rules['gcashAmount'] = [
                'required',
                'numeric',
                'min:0',
                function (string $attribute, mixed $value, Closure $fail) use ($method, $total): void {
                    if ($total === null || ! is_numeric($value)) {
                        return;
                    }

                    $totalCents = self::cents($total);
                    $gcashCents = self::cents((float) $value);

                    if ($method === TenderMethod::Gcash && $gcashCents !== $totalCents) {
                        $fail('The GCash amount must equal the total.');

                        return;
                    }

                    if ($method !== TenderMethod::Split) {
                        return;
                    }

                    if ($gcashCents <= 0) {
                        $fail('A split needs a GCash amount above zero.');

                        return;
                    }

                    $cash = $this->input('cash', 0);
                    $cashCents = is_numeric($cash) ? self::cents((float) $cash) : 0;

                    if ($cashCents + $gcashCents !== $totalCents) {
                        $fail('The cash and GCash amounts must add up to the total.');
                    }
                },
            ];
        }

        if ($method->needsGcashRef()) {
            $rules['gcashRef'] = ['required', 'string', 'max:64'];
        }

        return $rules;
    }

    public function tenderMethod(): TenderMethod
    {
        /** @var array{method: string} $validated */
        $validated = $this->validated();

        return TenderMethod::from($validated['method']);
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
