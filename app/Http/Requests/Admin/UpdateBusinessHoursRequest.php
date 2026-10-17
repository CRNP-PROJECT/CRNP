<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Settings\Weekday;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the "hours per weekday + force-close" form.
 *
 * A day may be left blank (closed); when both times are present the closing
 * time must be after the opening time, checked against the wall-clock strings
 * so no impossible window is persisted.
 */
final class UpdateBusinessHoursRequest extends FormRequest
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
        $days = implode(',', array_column(Weekday::cases(), 'value'));

        $rules = [
            'hours' => ['nullable', 'array:' . $days],
            'forceClose' => ['nullable', 'boolean'],
        ];

        foreach (Weekday::cases() as $day) {
            $rules["hours.{$day->value}"] = ['nullable', 'array:open,close'];
            $rules["hours.{$day->value}.open"] = ['nullable', 'date_format:H:i'];
            $rules["hours.{$day->value}.close"] = ['nullable', 'date_format:H:i'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var mixed $hours */
            $hours = $this->input('hours');

            if (! is_array($hours)) {
                return;
            }

            foreach (Weekday::cases() as $day) {
                $row = $hours[$day->value] ?? null;

                if (! is_array($row)) {
                    continue;
                }

                $open = $row['open'] ?? null;
                $close = $row['close'] ?? null;

                if (is_string($open) && is_string($close) && $open !== '' && $close !== '' && $open >= $close) {
                    $validator->errors()->add(
                        "hours.{$day->value}.close",
                        'The closing time must be after the opening time.',
                    );
                }
            }
        });
    }
}
