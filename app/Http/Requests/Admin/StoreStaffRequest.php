<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Rtdb\Contracts\RtdbClient;
use App\Staff\StaffDirectory;
use App\Staff\StaffRole;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the admin's "create staff" form: a name, a Google email, and a role.
 *
 * The email must be unique across the staff list — a duplicate is refused here
 * so a second account can never shadow an existing one.
 */
final class StoreStaffRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', $this->uniqueStaffEmail()],
            'role' => ['required', Rule::enum(StaffRole::class)],
        ];
    }

    private function uniqueStaffEmail(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value)) {
                return;
            }

            $id = StaffDirectory::idForEmail($value);

            if (app(RtdbClient::class)->get("staff/{$id}") !== null) {
                $fail('A staff member with that email already exists.');
            }
        };
    }
}
