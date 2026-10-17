<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Catalog\Promo;
use App\Catalog\PromoMode;
use App\Rtdb\Contracts\RtdbClient;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Validates the one-click **Add promo** control: a segmented mode (`percent` or
 * `amount`) and one number.
 *
 * The number is checked `0 < promoPrice < price` against the product's base
 * price here at the boundary, so an out-of-range promo is a form error and is
 * never persisted.
 */
final class SetProductPromoRequest extends FormRequest
{
    /**
     * Access is already settled by the `staff.role:admin` guard on the route.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::enum(PromoMode::class)],
            'value' => ['required', 'numeric', 'min:0', $this->derivablePromo()],
        ];
    }

    public function mode(): PromoMode
    {
        /** @var array{mode: string} $validated */
        $validated = $this->validated();

        return PromoMode::from($validated['mode']);
    }

    public function value(): float
    {
        /** @var array{value: numeric-string|int|float} $validated */
        $validated = $this->validated();

        return (float) $validated['value'];
    }

    private function derivablePromo(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $mode = $this->input('mode');
            $productId = $this->route('product');

            if (! is_string($mode) || ! is_string($productId) || ! is_numeric($value)) {
                return;
            }

            $promoMode = PromoMode::tryFrom($mode);

            if ($promoMode === null) {
                return;
            }

            $node = app(RtdbClient::class)->get("products/{$productId}");
            $price = is_array($node) ? ($node['price'] ?? null) : null;

            if (! is_numeric($price)) {
                return;
            }

            try {
                Promo::derive($promoMode, (float) $value, (float) $price);
            } catch (InvalidArgumentException) {
                $fail('A promo price must be greater than zero and less than the base price.');
            }
        };
    }
}
