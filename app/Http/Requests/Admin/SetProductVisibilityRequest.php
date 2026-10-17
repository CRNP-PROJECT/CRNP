<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the one-tap hide toggle's `hidden` flag.
 */
final class SetProductVisibilityRequest extends FormRequest
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
            'hidden' => ['required', 'boolean'],
        ];
    }

    public function hidden(): bool
    {
        /** @var array{hidden: mixed} $validated */
        $validated = $this->validated();

        return (bool) $validated['hidden'];
    }
}
