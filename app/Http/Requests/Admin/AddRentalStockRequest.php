<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Support\BusinessTime;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the Add stock receiving entry: supplier · qty · date (three inputs,
 * the quantity a stepper, the date defaulting today).
 */
final class AddRentalStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    #[\Override]
    protected function prepareForValidation(): void
    {
        // The date defaults today when the admin does not change it.
        if (! $this->filled('date')) {
            $this->merge(['date' => now(BusinessTime::timezone())->toDateString()]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'supplier' => ['required', 'string', 'max:120'],
            'qty' => ['required', 'integer', 'min:1', 'max:100000'],
            'date' => ['required', 'date_format:Y-m-d'],
        ];
    }

    public function supplier(): string
    {
        /** @var array{supplier: string} $validated */
        $validated = $this->validated();

        return trim($validated['supplier']);
    }

    public function quantity(): int
    {
        /** @var array{qty: int|string} $validated */
        $validated = $this->validated();

        return (int) $validated['qty'];
    }

    public function receivedOn(): string
    {
        /** @var array{date: string} $validated */
        $validated = $this->validated();

        return $validated['date'];
    }
}
