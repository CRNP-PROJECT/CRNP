<?php

declare(strict_types=1);

namespace App\Http\Requests\Cashier;

use App\Settings\ReservationArea;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates step 1 of reservation entry: the phone, and — for a new customer —
 * the name and area chips (≤3 inputs).
 *
 * The phone is the lookup key; a returning customer leaves the name blank and
 * the server prefills it from their past reservation. `type` is the tap-only
 * area chip.
 */
final class ReservationDetailsRequest extends FormRequest
{
    /**
     * Access is already settled by the `staff.role:cashier` guard on the route.
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
            'phone' => ['required', 'string', 'max:32'],
            'name' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', Rule::enum(ReservationArea::class)],
        ];
    }

    public function phone(): string
    {
        /** @var array{phone: string, name?: string|null, type?: string|null} $validated */
        $validated = $this->validated();

        return trim($validated['phone']);
    }

    public function name(): string
    {
        /** @var array{phone: string, name?: string|null, type?: string|null} $validated */
        $validated = $this->validated();

        return trim($validated['name'] ?? '');
    }

    public function area(): ?ReservationArea
    {
        /** @var array{phone: string, name?: string|null, type?: string|null} $validated */
        $validated = $this->validated();

        $type = $validated['type'] ?? null;

        return is_string($type) ? ReservationArea::tryFrom($type) : null;
    }
}
