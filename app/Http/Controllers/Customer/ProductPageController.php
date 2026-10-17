<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Customer\AddOnPrefs;
use App\Customer\Favorites;
use App\Customer\OrderingGate;
use App\Customer\Storefront;
use App\Customer\TopThree;
use App\Http\Controllers\Controller;
use App\Settings\SettingsRepository;
use App\Settings\StoreStatus;
use Illuminate\Contracts\View\View;

/**
 * The public product page: image, price/promo with the computed strike-through
 * and "% off", details, add-ons with the customer's last selection pre-checked,
 * and the same open/closed gate as the menu.
 *
 * Served from the same coalesced catalog cache entry the menu reads, so opening
 * a product never re-reads the base64 images.
 */
final class ProductPageController extends Controller
{
    public function __construct(
        private readonly Storefront $storefront,
        private readonly SettingsRepository $settings,
        private readonly StoreStatus $status,
        private readonly TopThree $topThree,
        private readonly Favorites $favorites,
        private readonly AddOnPrefs $addOnPrefs,
    ) {}

    public function __invoke(string $product): View
    {
        $found = $this->storefront->product($product);

        abort_if($found === null, 404);

        $settings = $this->settings->current();

        return view('menu.product', [
            'product' => $found,
            'category' => $this->storefront->categories()[$found->categoryId] ?? null,
            'gate' => OrderingGate::for($settings, $this->status, now()),
            'banner' => $settings->bannerVisible ? $settings->bannerText : null,
            'isTopThree' => in_array($found->id, $this->topThree->productIds(), true),
            'isFavorite' => $this->favorites->isFavorite(auth()->user(), $found->id),
            'selectedAddOns' => $this->addOnPrefs->forProduct(auth()->user(), $found->id),
        ]);
    }
}
