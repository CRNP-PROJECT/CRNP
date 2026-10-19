<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Catalog\Catalog;
use App\Catalog\Product;
use App\Orders\LineItem;
use App\Orders\PaymentMethod;
use App\Rtdb\Contracts\RtdbClient;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the historical-sales encoding form: a business date, a payment
 * method, and one or more product lines.
 *
 * The date may be any past business day but never the future — judged against
 * the **business date** (`Asia/Manila`), so an early-morning encoding is not
 * wrongly rejected because the UTC clock still reads the previous day. A
 * product is checked to exist; each line's unit price is optional, defaulting
 * to the product's current selling price, so the admin types a price only when
 * the historical one differed. Wholly blank lines are dropped before
 * validation, so the last row can be left empty.
 */
class StoreHistoricalSaleRequest extends FormRequest
{
    /**
     * Access is already settled by the `staff.role:admin` guard on the route.
     */
    public function authorize(): bool
    {
        return true;
    }

    #[\Override]
    protected function prepareForValidation(): void
    {
        $lines = $this->input('lines');

        if (! is_array($lines)) {
            return;
        }

        $cleaned = [];

        foreach ($lines as $row) {
            if (! is_array($row)) {
                continue;
            }

            $productId = $row['productId'] ?? '';
            $quantity = $row['quantity'] ?? '';

            // Drop a wholly blank line; keep a partial one so its missing field
            // is reported rather than silently discarded.
            if ((! is_string($productId) || trim($productId) === '') && $quantity === '') {
                continue;
            }

            $cleaned[] = $row;
        }

        $this->merge(['lines' => $cleaned]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:' . $this->businessToday()],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.productId' => ['required', 'string', $this->existingProduct()],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:9999'],
            'lines.*.unitPrice' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ];
    }

    public function saleDate(): string
    {
        /** @var array{date: string} $validated */
        $validated = $this->validated();

        return $validated['date'];
    }

    public function paymentMethod(): PaymentMethod
    {
        /** @var array{method: string} $validated */
        $validated = $this->validated();

        return PaymentMethod::from($validated['method']);
    }

    /**
     * The lines to record, each carrying its product's name snapshot.
     *
     * @return list<LineItem>
     */
    public function lines(): array
    {
        /** @var array<int, array{productId: string, quantity: int|numeric-string, unitPrice?: numeric-string|int|float|null}> $rows */
        $rows = $this->validated('lines');

        $catalog = app(Catalog::class);
        $lines = [];

        foreach ($rows as $row) {
            $product = $catalog->product($row['productId']);

            // Validated to exist; skip defensively rather than record a
            // nameless line.
            if (! $product instanceof Product) {
                continue;
            }

            $unitPrice = $row['unitPrice'] ?? null;

            $lines[] = new LineItem(
                productId: $product->id,
                name: $product->name,
                unitPrice: is_numeric($unitPrice) ? (float) $unitPrice : $product->sellingPrice(),
                quantity: (int) $row['quantity'],
            );
        }

        return $lines;
    }

    private function businessToday(): string
    {
        return CarbonImmutable::now(BusinessTime::timezone())->toDateString();
    }

    private function existingProduct(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value)) {
                return;
            }

            if (app(RtdbClient::class)->get("products/{$value}") === null) {
                $fail('Pick a product for this line.');
            }
        };
    }
}
