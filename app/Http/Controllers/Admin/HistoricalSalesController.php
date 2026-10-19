<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Catalog\Catalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreHistoricalSaleRequest;
use App\Orders\PaymentMethod;
use App\Stats\Actions\EncodeHistoricalSale;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * The **historical sales encoding** surface (see `config/settings.php`) — an
 * admin tool that records past sales into the `sales/{id}` ledger with the date
 * the admin picks.
 *
 * The `historical.sales` middleware answers 404 when the feature is off.
 */
final class HistoricalSalesController extends Controller
{
    public function __construct(private readonly Catalog $catalog) {}

    public function create(): View
    {
        return view('admin.reports.historical', [
            'products' => $this->catalog->products(),
            'methods' => PaymentMethod::cases(),
            'today' => CarbonImmutable::now(BusinessTime::timezone())->toDateString(),
        ]);
    }

    public function store(StoreHistoricalSaleRequest $request, EncodeHistoricalSale $encode): RedirectResponse
    {
        $encode($request->saleDate(), $request->paymentMethod(), $request->lines());

        return redirect()
            ->route('admin.historical.create')
            ->with('status', 'Historical sale encoded.');
    }
}
