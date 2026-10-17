<?php

declare(strict_types=1);

namespace App\Http\Requests\Cashier;

use App\Reservations\ReservationDraft;
use App\Settings\ReservationArea;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates step 2 of reservation entry — the date/time and the party size
 * (≤2 inputs) — and carries the step-1 values forward in hidden fields.
 *
 * The `when` field is a local `Y-m-d\TH:i` datetime; it is split into the
 * stored `date` (`Y-m-d`) and `time` (`H:i`). The conflict and duplicate check
 * is not a validation rule here — it runs in the action, because it needs the
 * stored reservations, and its reason is surfaced as the `reservation` error.
 */
final class StoreReservationRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:32'],
            'type' => ['required', Rule::enum(ReservationArea::class)],
            'preferredType' => ['nullable', Rule::enum(ReservationArea::class)],
            'when' => ['required', 'date_format:Y-m-d\TH:i'],
            'party' => ['required', 'integer', 'min:1', 'max:10000'],
        ];
    }

    public function draft(): ReservationDraft
    {
        /** @var array{name: string, phone: string, type: string, preferredType?: string|null, when: string, party: int|string} $validated */
        $validated = $this->validated();

        $at = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $validated['when']) ?? CarbonImmutable::now();

        $type = ReservationArea::from($validated['type']);
        $preferred = $validated['preferredType'] ?? null;
        $preferredType = is_string($preferred) ? ReservationArea::tryFrom($preferred) : null;

        return new ReservationDraft(
            name: trim($validated['name']),
            phone: trim($validated['phone']),
            type: $type,
            preferredType: $preferredType ?? $type,
            date: $at->toDateString(),
            time: $at->format('H:i'),
            party: (int) $validated['party'],
        );
    }
}
