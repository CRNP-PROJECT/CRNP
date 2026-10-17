<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the GCash payment block: the number to copy/pay-by-number and the
 * official QR image. The QR is optional on a re-save so the admin can edit the
 * number without re-uploading the image; when present it is resized server-side
 * and stored as base64 — no raw file ever reaches RTDB.
 */
final class UpdatePaymentRequest extends FormRequest
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
            'gcashNumber' => ['required', 'string', 'max:40'],
            'qr' => ['nullable', 'image', 'max:5120'],
        ];
    }
}
