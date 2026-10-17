<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the admin's category form: a name and a sort order.
 */
final class SaveCategoryRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:60'],
            'order' => ['required', 'integer', 'min:0', 'max:999'],
        ];
    }
}
