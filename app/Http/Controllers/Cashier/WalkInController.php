<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cashier;

use App\Catalog\Catalog;
use App\Catalog\Product;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cashier\AddWalkInItemRequest;
use App\Http\Requests\Cashier\ChangeWalkInServiceTypeRequest;
use App\Http\Requests\Cashier\OpenWalkInRequest;
use App\Http\Requests\Cashier\SettleWalkInRequest;
use App\Orders\Actions\AddItemToWalkIn;
use App\Orders\Actions\ChangeWalkInServiceType;
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
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The walk-in ring-up: tap tiles to build the order, settle through the one
 * Cash + GCash form, and print the receipt (PLAN §4 *Cashier POS*).
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
        $id = $openWalkIn($this->cashier(), $request->table(), $request->serviceType());

        return redirect()->route('cashier.walkins.show', ['walkin' => $id]);
    }

    public function show(Request $request, string $walkin): View
    {
        $categoryId = $request->query('category');
        $categoryId = is_string($categoryId) && $categoryId !== '' ? $categoryId : null;
        $categories = $this->catalog->categories();

        return view('cashier.walkin.show', [
            'order' => $this->find($walkin),
            'menu' => $this->menu($categoryId),
            'categories' => $categories,
            'selectedCategory' => $categoryId,
            'selectedCategoryName' => $categoryId === null ? null : ($categories[$categoryId]->name ?? null),
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

        $addItem($walkin, $request->productId(), $request->quantity(), $request->addOnNames());

        return redirect()->route('cashier.walkins.show', ['walkin' => $walkin]);
    }

    public function removeItem(string $walkin, string $line, RemoveItemFromWalkIn $removeItem): RedirectResponse
    {
        $order = $this->find($walkin);

        if (! $order->isPending() || ! ctype_digit($line) || ! array_key_exists((int) $line, $order->lines)) {
            return redirect()->route('cashier.walkins.show', ['walkin' => $walkin]);
        }

        $removeItem($walkin, (int) $line);

        return redirect()->route('cashier.walkins.show', ['walkin' => $walkin]);
    }

    /**
     * Switch the open walk-in between dine-in and take-out in place, so the
     * cart survives a change of mind. Guarded to a pending order like the
     * other ring-up writes.
     */
    public function changeServiceType(
        ChangeWalkInServiceTypeRequest $request,
        string $walkin,
        ChangeWalkInServiceType $change,
    ): RedirectResponse {
        if (! $this->find($walkin)->isPending()) {
            return redirect()->route('cashier.walkins.show', ['walkin' => $walkin]);
        }

        $change($walkin, $request->serviceType());

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

        $settle(
            $walkin,
            $request->cash(),
            $request->gcashAmount(),
            $request->reference(),
            $this->cashier(),
        );

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

    /**
     * The ring-up menu, optionally narrowed to one category — the cashier's
     * chip filter. A null category leaves the whole menu (the 86 board), so a
     * product whose category was deleted is never silently dropped.
     *
     * @return array<string, Product>
     */
    private function menu(?string $categoryId): array
    {
        $menu = $this->catalog->menu();

        if ($categoryId === null) {
            return $menu;
        }

        return array_filter(
            $menu,
            static fn(Product $product): bool => $product->categoryId === $categoryId,
        );
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
