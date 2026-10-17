<?php

declare(strict_types=1);

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the add-on selection a customer saves for a product.
 *
 * The selection is a list of add-on names the product actually offers; the
 * controller only ever stores `$request->validated()`-derived names, so a
 * tampered payload cannot write arbitrary keys under `users/{uid}`.
 */
final class SaveAddOnPrefsRequest extends FormRequest
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
            'addOns' => ['array'],
            'addOns.*' => ['string', 'max:80'],
        ];
    }

    /**
     * The selected add-on names, deduplicated and capped to a sane count.
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
