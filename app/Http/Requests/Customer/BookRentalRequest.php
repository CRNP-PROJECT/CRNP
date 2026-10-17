<?php

declare(strict_types=1);

namespace App\Http\Requests\Customer;

use App\Rentals\RentalPeriod;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a rental booking: the date range and the quantity (≤3 inputs)
 * (PLAN §4 *Rentals*).
 *
 * The range is validated as plain `Y-m-d` dates and the quantity as a positive
 * integer; the total is computed server-side by the action, never accepted from
 * the client. The availability re-check runs in the action — it needs the stored
 * bookings — and its reason is surfaced as the `rental` error.
 */
final class BookRentalRequest extends FormRequest
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
            'dateFrom' => ['required', 'date_format:Y-m-d'],
            'dateTo' => ['required', 'date_format:Y-m-d', 'after_or_equal:dateFrom'],
            'qty' => ['required', 'integer', 'min:1', 'max:10000'],
        ];
    }

    public function period(): RentalPeriod
    {
        /** @var array{dateFrom: string, dateTo: string} $validated */
        $validated = $this->validated();

        return RentalPeriod::fromStrings($validated['dateFrom'], $validated['dateTo']);
    }

    public function quantity(): int
    {
        /** @var array{qty: int|string} $validated */
        $validated = $this->validated();

        return (int) $validated['qty'];
    }
}
