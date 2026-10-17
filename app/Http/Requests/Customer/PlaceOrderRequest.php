<?php

declare(strict_types=1);

namespace App\Http\Requests\Customer;

use App\Orders\PickupTime;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the Place order POST: a client-generated idempotency key and an
 * optional pickup time (PLAN §4 *Customer site* — place order).
 *
 * The pickup time is validated **server-side** against the same now + 15 min
 * floor the picker shows, so a too-soon time can never reach payment. The
 * idempotency key is required: a checkout attempt must carry one, so a
 * timeout-then-retry can never open a second window.
 */
final class PlaceOrderRequest extends FormRequest
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
            // The key becomes an RTDB node key at `idem/{key}`, so it is bounded
            // to characters RTDB accepts — never `. $ # [ ] /` or control chars.
            'idempotencyKey' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9_-]+$/'],
            'pickup' => ['nullable', 'string', 'max:40'],
        ];
    }

    /**
     * The pickup time as a value object, validated against the earliest
     * selectable instant — a too-soon time is a field error, not a 500.
     */
    public function pickup(): PickupTime
    {
        return PickupTime::fromInput($this->validated()['pickup'] ?? null, now());
    }

    public function idempotencyKey(): string
    {
        /** @var string $key */
        $key = $this->validated()['idempotencyKey'];

        return trim($key);
    }

    /**
     * Turn the too-soon pickup refusal into a field error on `pickup`.
     */
    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('pickup')) {
                return;
            }

            try {
                $this->pickup();
            } catch (\InvalidArgumentException $exception) {
                $validator->errors()->add('pickup', $exception->getMessage());
            }
        });
    }
}
