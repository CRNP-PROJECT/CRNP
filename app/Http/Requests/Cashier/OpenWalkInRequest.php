<?php

declare(strict_types=1);

namespace App\Http\Requests\Cashier;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the walk-in ring-up POST: the optional table name (PLAN §4 *Cashier
 * POS*; CONTEXT — *Walk-in order*).
 *
 * A walk-in may optionally carry a **table name** (parent story 164) — an
 * onsite customer seated at a table. The field is optional: the common counter
 * order types nothing, so a blank value is accepted and stored as null.
 */
final class OpenWalkInRequest extends FormRequest
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
            'table' => ['nullable', 'string', 'max:60'],
        ];
    }

    public function table(): ?string
    {
        $table = $this->validated()['table'] ?? null;

        if (! is_string($table)) {
            return null;
        }

        $table = trim($table);

        return $table !== '' ? $table : null;
    }
}
