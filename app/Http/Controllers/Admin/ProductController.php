<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Catalog\Actions\DeleteProduct;
use App\Catalog\Actions\RemoveProductPromo;
use App\Catalog\Actions\SaveProduct;
use App\Catalog\Actions\SetProductPromo;
use App\Catalog\Actions\SetProductVisibility;
use App\Catalog\Catalog;
use App\Catalog\PromoMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveProductRequest;
use App\Http\Requests\Admin\SetProductPromoRequest;
use App\Http\Requests\Admin\SetProductVisibilityRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * The admin's product surface: list, create/edit, the one-tap hide toggle, and
 * the one-click Add promo control.
 *
 * The product form stays promo-free; a promo is added and removed through its
 * own endpoints. Every write routes through an action that evicts the catalog
 * cache, so a price or visibility change reaches the menu on the next request.
 */
final class ProductController extends Controller
{
    public function __construct(private readonly Catalog $catalog) {}

    public function index(): View
    {
        return view('admin.products.index', [
            'products' => $this->catalog->products(),
            'categories' => $this->catalog->categories(),
        ]);
    }

    public function create(): View
    {
        return view('admin.products.form', [
            'product' => null,
            'categories' => $this->catalog->categories(),
            'promoModes' => PromoMode::cases(),
        ]);
    }

    public function store(SaveProductRequest $request, SaveProduct $saveProduct): RedirectResponse
    {
        $saveProduct(null, $request->productInput());

        return redirect()
            ->route('admin.products.index')
            ->with('status', 'Product added.');
    }

    public function edit(string $product): View
    {
        $found = $this->catalog->product($product);

        abort_if($found === null, 404);

        return view('admin.products.form', [
            'product' => $found,
            'categories' => $this->catalog->categories(),
            'promoModes' => PromoMode::cases(),
        ]);
    }

    public function update(SaveProductRequest $request, string $product, SaveProduct $saveProduct): RedirectResponse
    {
        $saveProduct($product, $request->productInput());

        return redirect()
            ->route('admin.products.index')
            ->with('status', 'Product updated.');
    }

    public function destroy(string $product, DeleteProduct $deleteProduct): RedirectResponse
    {
        $deleteProduct($product);

        return redirect()
            ->route('admin.products.index')
            ->with('status', 'Product deleted.');
    }

    public function visibility(
        SetProductVisibilityRequest $request,
        string $product,
        SetProductVisibility $setVisibility,
    ): RedirectResponse {
        $setVisibility($product, $request->hidden());

        return redirect()
            ->route('admin.products.index')
            ->with('status', $request->hidden() ? 'Product hidden from the menu.' : 'Product back on the menu.');
    }

    public function addPromo(
        SetProductPromoRequest $request,
        string $product,
        SetProductPromo $setPromo,
    ): RedirectResponse {
        $setPromo($product, $request->mode(), $request->value());

        return redirect()
            ->route('admin.products.index')
            ->with('status', 'Promo added.');
    }

    public function removePromo(string $product, RemoveProductPromo $removePromo): RedirectResponse
    {
        $removePromo($product);

        return redirect()
            ->route('admin.products.index')
            ->with('status', 'Promo removed.');
    }
}
