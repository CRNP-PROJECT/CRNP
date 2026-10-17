<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cashier;

use App\Catalog\Catalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cashier\AddWalkInItemRequest;
use App\Http\Requests\Cashier\OpenWalkInRequest;
use App\Http\Requests\Cashier\SettleWalkInRequest;
use App\Orders\Actions\AddItemToWalkIn;
use App\Orders\Actions\OpenWalkIn;
use App\Orders\Actions\RemoveItemFromWalkIn;
use App\Orders\Actions\SettleWalkIn;
use App\Orders\HandledBy;
use App\Orders\Order;
use App\Orders\Orders;
use App\Staff\Staff;
use App\Staff\StaffDirectory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The walk-in ring-up: tap tiles to build the order, settle with one typed cash
 * number, and print the receipt (PLAN §4 *Cashier POS*).
 *
 * The controller validates at the boundary, delegates to the order actions, and
 * responds. Every write is attributed to the cashier on duty (`handledBy`),
 * resolved from `staff/{uid}` — the same seam the register header names.
 */
final class WalkInController extends Controller
{
    public function __construct(
        private readonly Orders $orders,
        private readonly Catalog $catalog,
        private readonly StaffDirectory $directory,
    ) {}

    public function store(OpenWalkInRequest $request, OpenWalkIn $openWalkIn): RedirectResponse
    {
        $id = $openWalkIn($this->cashier(), $request->table());

        return redirect()->route('cashier.walkins.show', ['walkin' => $id]);
    }

    public function show(string $walkin): View
    {
        return view('cashier.walkin.show', [
            'order' => $this->find($walkin),
            'menu' => $this->catalog->menu(),
            'cashier' => $this->directory->forUser(auth()->user()),
        ]);
    }

    public function addItem(AddWalkInItemRequest $request, string $walkin, AddItemToWalkIn $addItem): RedirectResponse
    {
        // Status-guarded: a stale tile tap on a settled order is a friendly
        // no-op, never a 500 from an action that only accepts a pending order.
        if (! $this->find($walkin)->isPending()) {
            return redirect()->route('cashier.walkins.show', ['walkin' => $walkin]);
        }

        $addItem($walkin, $request->productId(), $request->quantity());

        return redirect()->route('cashier.walkins.show', ['walkin' => $walkin]);
    }

    public function removeItem(string $walkin, string $product, RemoveItemFromWalkIn $removeItem): RedirectResponse
    {
        if (! $this->find($walkin)->isPending()) {
            return redirect()->route('cashier.walkins.show', ['walkin' => $walkin]);
        }

        $removeItem($walkin, $product);

        return redirect()->route('cashier.walkins.show', ['walkin' => $walkin]);
    }

    public function settle(SettleWalkInRequest $request, string $walkin, SettleWalkIn $settle): RedirectResponse
    {
        $order = $this->find($walkin);

        // Status-guarded: a stale or duplicate tap is a friendly no-op, never a
        // second write and never a spurious validation error on a settled order.
        if (! $order->isPending()) {
            return redirect()->route('cashier.walkins.receipt', ['walkin' => $walkin]);
        }

        $settle($walkin, $request->cash(), $request->reference(), $this->cashier());

        return redirect()->route('cashier.walkins.receipt', ['walkin' => $walkin]);
    }

    public function receipt(string $walkin): View
    {
        return view('cashier.walkin.receipt', [
            'order' => $this->find($walkin),
            'cashier' => $this->directory->forUser(auth()->user()),
        ]);
    }

    private function find(string $id): Order
    {
        $order = $this->orders->find($id);

        if ($order === null) {
            throw new NotFoundHttpException("No order with id [{$id}].");
        }

        return $order;
    }

    private function cashier(): HandledBy
    {
        $staff = $this->directory->forUser(auth()->user());

        if (! $staff instanceof Staff) {
            abort(403, 'This area is for staff accounts only.');
        }

        return HandledBy::fromStaff($staff);
    }
}
