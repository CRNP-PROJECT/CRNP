<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Settings\ReservationArea;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the reservation-capacity form: one seat count per area, feeding the
 * public occupancy view. Blank areas fall back to the smart default.
 */
final class UpdateCapacitiesRequest extends FormRequest
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
        $areas = implode(',', array_column(ReservationArea::cases(), 'value'));

        $rules = ['capacities' => ['nullable', 'array:' . $areas]];

        foreach (ReservationArea::cases() as $area) {
            $rules["capacities.{$area->value}"] = ['nullable', 'integer', 'min:0', 'max:10000'];
        }

        return $rules;
    }
}
