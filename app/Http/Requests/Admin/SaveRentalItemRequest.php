<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Rentals\RentalInput;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the admin's rental-item form: name · image · price/day · units.
 *
 * An image is required on create and optional on edit (the stored one is kept
 * when none is uploaded). `units` is the on-hand count; the stepper and Add
 * stock own it day to day, so an edit that only changes the name or price keeps
 * the current figure.
 */
class SaveRentalItemRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'pricePerDay' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'units' => ['required', 'integer', 'min:0', 'max:100000'],
            'image' => [$this->imageRequired(), 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
        ];
    }

    public function rentalInput(): RentalInput
    {
        /** @var array{name: string, pricePerDay: numeric-string|int|float, units: int|string} $validated */
        $validated = $this->validated();

        return new RentalInput(
            name: $validated['name'],
            pricePerDay: (float) $validated['pricePerDay'],
            units: (int) $validated['units'],
            image: $this->file('image'),
        );
    }

    private function imageRequired(): string
    {
        return $this->route('item') === null ? 'required' : 'nullable';
    }
}
