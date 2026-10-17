<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Staff\StaffRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the admin's "change staff role" form (create/demote).
 */
final class UpdateStaffRoleRequest extends FormRequest
{
    /**
     * Access is already settled by the `staff.admin` role guard on the route.
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
            'role' => ['required', Rule::enum(StaffRole::class)],
        ];
    }
}
