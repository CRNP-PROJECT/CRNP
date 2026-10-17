<?php

declare(strict_types=1);

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a cart stepper / add-on update from the cart screen.
 *
 * The customer supplies only a product id, a quantity and the add-on names
 * chosen — never a price. The controller rebuilds the cart against the current
 * catalog, so a tampered payload cannot invent a price.
 */
final class UpdateCartRequest extends FormRequest
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
        return [
            'product' => ['required', 'string', 'max:64'],
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'addOns' => ['array'],
            'addOns.*' => ['string', 'max:80'],
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
        $quantity = $this->validated()['quantity'];

        return is_numeric($quantity) ? (int) $quantity : 0;
    }

    /**
     * The chosen add-on names, deduplicated and capped.
     *
     * @return list<string>
     */
    public function addOnNames(): array
    {
        /** @var array<int, mixed> $raw */
        $raw = $this->validated()['addOns'] ?? [];

        $names = [];

        foreach (array_slice($raw, 0, 20) as $name) {
            if (is_string($name) && trim($name) !== '') {
                $names[] = trim($name);
            }
        }

        return array_values(array_unique($names));
    }
}
