<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AddRentalStockRequest;
use App\Http\Requests\Admin\AdjustRentalStockRequest;
use App\Http\Requests\Admin\SaveRentalItemRequest;
use App\Rentals\Actions\AddRentalStock;
use App\Rentals\Actions\AdjustRentalStock;
use App\Rentals\Actions\DeleteRentalItem;
use App\Rentals\Actions\SaveRentalItem;
use App\Rentals\Rentals;
use App\Support\BusinessTime;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * The admin's rental inventory surface (PLAN §4 *Admin* — Inventory).
 *
 * Inventory is rental units only. The list shows each item's name · image ·
 * price/day · units with an inline `− / +` stepper, an **Add stock** receiving
 * entry (supplier · qty · date), and a **low-stock list carrying the restock
 * button**. Rental item CRUD lives here next to Add stock. Every write routes
 * through an action that evicts the shared availability cache, so the public
 * catalog reflects the change on the next request.
 */
final class RentalController extends Controller
{
    public function __construct(private readonly Rentals $rentals) {}

    public function index(): View
    {
        $items = $this->rentals->all();
        $threshold = self::lowStockThreshold();

        $lowStock = array_filter(
            $items,
            static fn($item): bool => $item->units <= $threshold,
        );

        return view('admin.rentals.index', [
            'items' => $items,
            'lowStock' => $lowStock,
            'threshold' => $threshold,
            'today' => now(BusinessTime::timezone())->toDateString(),
        ]);
    }

    public function create(): View
    {
        return view('admin.rentals.form', ['item' => null]);
    }

    public function store(SaveRentalItemRequest $request, SaveRentalItem $save): RedirectResponse
    {
        $save(null, $request->rentalInput());

        return redirect()
            ->route('admin.rentals.index')
            ->with('status', 'Rental item added.');
    }

    public function edit(string $item): View
    {
        $found = $this->rentals->find($item);

        abort_if($found === null, 404);

        return view('admin.rentals.form', ['item' => $found]);
    }

    public function update(SaveRentalItemRequest $request, string $item, SaveRentalItem $save): RedirectResponse
    {
        $save($item, $request->rentalInput());

        return redirect()
            ->route('admin.rentals.index')
            ->with('status', 'Rental item updated.');
    }

    public function destroy(string $item, DeleteRentalItem $delete): RedirectResponse
    {
        $delete($item);

        return redirect()
            ->route('admin.rentals.index')
            ->with('status', 'Rental item deleted.');
    }

    /**
     * The inline `− / +` stepper: adjust on-hand units by a signed delta.
     */
    public function adjust(AdjustRentalStockRequest $request, string $item, AdjustRentalStock $adjust): RedirectResponse
    {
        $adjust($item, $request->delta());

        return redirect()
            ->route('admin.rentals.index')
            ->with('status', 'Stock adjusted.');
    }

    /**
     * Add stock: a receiving entry (supplier · qty · date) that raises units.
     */
    public function stock(AddRentalStockRequest $request, string $item, AddRentalStock $addStock): RedirectResponse
    {
        $addStock($item, $request->supplier(), $request->quantity(), $request->receivedOn());

        return redirect()
            ->route('admin.rentals.index')
            ->with('status', 'Stock received.');
    }

    private static function lowStockThreshold(): int
    {
        $threshold = config('rentals.low_stock_threshold');

        return is_numeric($threshold) ? (int) $threshold : 3;
    }
}
