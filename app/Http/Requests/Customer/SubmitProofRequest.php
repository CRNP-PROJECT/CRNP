<?php

declare(strict_types=1);

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a proof-of-payment upload: the GCash screenshot, the amount paid,
 * and the GCash ref# (PLAN §4 *Customer site* — proof of payment).
 *
 * The amount is required and numeric — it prefills with the order total on the
 * client, so a customer edits only what differs. The ref# is optional at the
 * boundary: a blank ref# is exactly the case that lands a flagged hold, so it
 * must reach the action rather than be refused as a validation error. The image
 * is bounded to a sane upload size; the server re-encodes it to the proof
 * ceiling regardless of what the client sent.
 */
final class SubmitProofRequest extends FormRequest
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
            'image' => ['required', 'image', 'max:10240'],
            'amount' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'ref' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function amount(): float
    {
        /** @var numeric-string|int|float $amount */
        $amount = $this->validated()['amount'];

        return round((float) $amount, 2);
    }

    public function reference(): ?string
    {
        $ref = $this->validated()['ref'] ?? null;

        return is_string($ref) ? $ref : null;
    }

    /**
     * The uploaded image bytes, or null when unreadable.
     */
    public function imageBytes(): ?string
    {
        $bytes = $this->file('image')?->get();

        return is_string($bytes) && $bytes !== '' ? $bytes : null;
    }
}
