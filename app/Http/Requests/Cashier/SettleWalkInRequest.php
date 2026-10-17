<?php

declare(strict_types=1);

namespace App\Http\Requests\Cashier;

use App\Orders\Order;
use App\Orders\Orders;
use App\Orders\SplitTender;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the walk-in settle POST: the one cash number typed, and the GCash
 * ref# **only when the derived GCash leg is positive** (PLAN §4 *Cashier POS* —
 * split tender).
 *
 * The cashier types exactly one number — the cash handed over. Everything else
 * derives: `cash ≥ total` → all-cash with computed change and no GCash leg (so
 * no ref# is required); `cash < total` → `GCash = total − cash` with a ref#
 * **required**. The conditional rule reads the order's total, so a split without
 * evidence is refused at the boundary before any write. A stale or duplicate
 * tap on a settled order is short-circuited to no rules so the controller's
 * status guard returns the friendly no-op rather than a spurious error.
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

        $rules = ['cash' => ['required', 'numeric', 'min:0']];

        if ($this->requiresGcashRef()) {
            $rules['gcashRef'] = ['required', 'string', 'max:64'];
        }

        return $rules;
    }

    public function cash(): float
    {
        /** @var numeric-string|int|float $cash */
        $cash = $this->validated()['cash'];

        return (float) $cash;
    }

    public function reference(): ?string
    {
        $ref = $this->validated()['gcashRef'] ?? null;

        return is_string($ref) ? $ref : null;
    }

    /**
     * Whether the typed cash leaves a positive GCash leg, which demands a ref#.
     */
    private function requiresGcashRef(): bool
    {
        $order = $this->pendingOrder();

        if ($order === null) {
            return false;
        }

        $cash = $this->input('cash');
        $cashTyped = is_numeric($cash) ? (float) $cash : 0.0;

        return SplitTender::for($order->total, $cashTyped)->requiresGcashRef;
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
