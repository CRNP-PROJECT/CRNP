<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Catalog\AddOn;
use App\Catalog\Catalog;
use App\Catalog\Product;
use App\Orders\LineItem;
use App\Orders\PaymentMethod;
use App\Orders\ServiceType;
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
 *
 * A line may also carry the customer's **add-ons** — the same per-product
 * add-ons the register and the customer cart offer. They are validated to be
 * ones the product actually offers, by name, and ride on the line to the
 * receipt, so an encoded sale reads exactly like a live one.
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
            'serviceType' => ['required', Rule::enum(ServiceType::class)],
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.productId' => ['required', 'string', $this->existingProduct()],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:9999'],
            'lines.*.unitPrice' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'lines.*.addOns' => ['nullable', 'array', 'max:50'],
            'lines.*.addOns.*' => ['string', $this->offeredAddOn()],
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

    public function serviceType(): ServiceType
    {
        /** @var array{serviceType: string} $validated */
        $validated = $this->validated();

        return ServiceType::from($validated['serviceType']);
    }

    /**
     * The lines to record, each carrying its product's name snapshot and the
     * customer's chosen add-ons.
     *
     * @return list<LineItem>
     */
    public function lines(): array
    {
        /** @var array<int, array{productId: string, quantity: int|numeric-string, unitPrice?: numeric-string|int|float|null, addOns?: list<string>}> $rows */
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
                addOns: $this->offeredAddOns($product, $row['addOns'] ?? []),
            );
        }

        return $lines;
    }

    /**
     * The chosen add-ons the product offers, in the product's own order — the
     * same normalization the customer cart applies, so an encoded line prices
     * its add-ons identically to a live one.
     *
     * @param  list<string>  $chosen
     * @return list<AddOn>
     */
    private function offeredAddOns(Product $product, array $chosen): array
    {
        $addOns = [];

        foreach ($product->addOns as $addOn) {
            if (in_array($addOn->name, $chosen, true)) {
                $addOns[] = $addOn;
            }
        }

        return $addOns;
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

    /**
     * An add-on is accepted only when the line's own product offers it, so a
     * stale or hand-typed name can never invent a price. The add-on's parent
     * line is read from its dotted path (`lines.3.addOns.0` → `lines.3`).
     */
    private function offeredAddOn(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value)) {
                return;
            }

            $lineKey = implode('.', array_slice(explode('.', $attribute), 0, 2));
            $productId = $this->input("{$lineKey}.productId");

            if (! is_string($productId)) {
                return;
            }

            $product = app(Catalog::class)->product($productId);

            if (! $product instanceof Product) {
                return;
            }

            $offered = array_map(
                static fn(AddOn $addOn): string => $addOn->name,
                $product->addOns,
            );

            if (! in_array($value, $offered, true)) {
                $fail("{$product->name} does not offer that add-on.");
            }
        };
    }
}
