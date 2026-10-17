<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a scheduled date-range closure (a holiday configured once).
 *
 * Dates are inclusive; the end may not fall before the start.
 */
final class StoreClosureRequest extends FormRequest
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
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'label' => ['nullable', 'string', 'max:60'],
        ];
    }
}
