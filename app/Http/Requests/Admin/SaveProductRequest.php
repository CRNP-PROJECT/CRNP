<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Catalog\AddOn;
use App\Catalog\ProductInput;
use App\Rtdb\Contracts\RtdbClient;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the admin's product form: name, description, price, category, image,
 * and add-on rows.
 *
 * The form is deliberately promo-free — a promo is added through its own
 * one-click control — so promo fields posted here are never validated or
 * persisted. Blank add-on rows are dropped before validation so an admin can
 * leave the last row empty.
 */
class SaveProductRequest extends FormRequest
{
    /**
     * Access is already settled by the `staff.role:admin` guard on the route.
     */
    public function authorize(): bool
    {
        return true;
    }

    #[\Override]
    protected function prepareForValidation(): void
    {
        $addOns = $this->input('addOns');

        if (! is_array($addOns)) {
            return;
        }

        $cleaned = [];

        foreach ($addOns as $row) {
            if (! is_array($row)) {
                continue;
            }

            $name = $row['name'] ?? '';
            $price = $row['price'] ?? '';

            // Drop a wholly blank row; keep a partial one so its missing field
            // is reported rather than silently discarded.
            if ((! is_string($name) || trim($name) === '') && $price === '') {
                continue;
            }

            $cleaned[] = $row;
        }

        $this->merge(['addOns' => $cleaned]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'categoryId' => ['required', 'string', $this->existingCategory()],
            'image' => [$this->imageRequired(), 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
            'addOns' => ['nullable', 'array', 'max:20'],
            'addOns.*.name' => ['required', 'string', 'max:60'],
            'addOns.*.price' => ['required', 'numeric', 'min:0', 'max:99999.99'],
            'theme' => ['nullable', 'string', 'max:60'],
        ];
    }

    /**
     * A new product needs an image; an edit keeps the stored one when none is
     * uploaded.
     */
    public function productInput(): ProductInput
    {
        /** @var array{name: string, description?: string|null, price: numeric-string|int|float, categoryId: string, addOns?: array<int, array{name: string, price: numeric-string|int|float}>, theme?: string|null} $validated */
        $validated = $this->validated();

        return new ProductInput(
            name: $validated['name'],
            description: $validated['description'] ?? '',
            price: (float) $validated['price'],
            categoryId: $validated['categoryId'],
            addOns: $this->addOns($validated['addOns'] ?? []),
            image: $this->file('image'),
            theme: is_string($validated['theme'] ?? null) && $validated['theme'] !== '' ? $validated['theme'] : null,
        );
    }

    private function imageRequired(): string
    {
        return $this->route('product') === null ? 'required' : 'nullable';
    }

    /**
     * @param  array<int, array{name: string, price: numeric-string|int|float}>  $rows
     * @return list<AddOn>
     */
    private function addOns(array $rows): array
    {
        $addOns = [];

        foreach ($rows as $row) {
            $addOns[] = new AddOn(trim($row['name']), (float) $row['price']);
        }

        return $addOns;
    }

    private function existingCategory(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value)) {
                return;
            }

            if (app(RtdbClient::class)->get("categories/{$value}") === null) {
                $fail('Pick a category for this product.');
            }
        };
    }
}
