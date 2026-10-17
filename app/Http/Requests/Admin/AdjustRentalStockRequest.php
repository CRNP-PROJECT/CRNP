<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the inline `− / +` stock stepper: a signed delta.
 */
final class AdjustRentalStockRequest extends FormRequest
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
            'delta' => ['required', 'integer', 'in:-1,1'],
        ];
    }

    public function delta(): int
    {
        /** @var array{delta: int|string} $validated */
        $validated = $this->validated();

        return (int) $validated['delta'];
    }
}
