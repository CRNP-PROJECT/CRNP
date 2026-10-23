<?php

declare(strict_types=1);

namespace App\Http\Requests\Cashier;

use App\Orders\Orders;
use App\Orders\ServiceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the in-POS service-type switch: dine-in or take-out, changed on the
 * open walk-in rather than at the register (PLAN §4 *Cashier POS*).
 *
 * A stale switch on a settled order is short-circuited to no rules so the
 * controller's status guard returns the friendly no-op rather than a spurious
 * validation error.
 */
final class ChangeWalkInServiceTypeRequest extends FormRequest
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
        if (! $this->orderIsPending()) {
            return [];
        }

        return [
            'serviceType' => ['required', Rule::enum(ServiceType::class)],
        ];
    }

    public function serviceType(): ServiceType
    {
        /** @var string $serviceType */
        $serviceType = $this->validated()['serviceType'];

        return ServiceType::from($serviceType);
    }

    private function orderIsPending(): bool
    {
        $walkin = $this->route('walkin');

        return is_string($walkin) && app(Orders::class)->find($walkin)?->isPending() === true;
    }
}
