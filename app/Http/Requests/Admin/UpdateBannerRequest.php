<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the announcement-banner form: admin-written promo text plus a
 * show/hide toggle.
 */
final class UpdateBannerRequest extends FormRequest
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
            'banner' => ['nullable', 'string', 'max:280'],
            'bannerVisible' => ['nullable', 'boolean'],
        ];
    }
}
