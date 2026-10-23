<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Customer\Favorites;
use App\Customer\OrderingGate;
use App\Customer\Storefront;
use App\Customer\TopThree;
use App\Http\Controllers\Controller;
use App\Settings\SettingsRepository;
use App\Settings\StoreStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * The public landing page: the menu, grouped under category titles, filtered by
 * a category dropdown, served from the coalesced catalog cache.
 *
 * The open/closed badge and the place-order gate compute from #20's settings
 * (hours, force-close, closures, the GCash payment block); the announcement
 * banner and the Top 3 / promo tags render from the same cached reads. Browsing
 * never requires a sign-in.
 */
final class MenuController extends Controller
{
    public function __construct(
        private readonly Storefront $storefront,
        private readonly SettingsRepository $settings,
        private readonly StoreStatus $status,
        private readonly TopThree $topThree,
        private readonly Favorites $favorites,
    ) {}

    public function __invoke(Request $request): View
    {
        $categoryId = $request->query('category');
        $categoryId = is_string($categoryId) && $categoryId !== '' ? $categoryId : null;

        $settings = $this->settings->current();
        $categories = $this->storefront->categories();

        return view('menu.index', [
            'groups' => $this->storefront->groups($categoryId),
            'categories' => $categories,
            'selectedCategory' => $categoryId,
            'selectedCategoryName' => $categoryId === null ? null : ($categories[$categoryId]->name ?? null),
            'gate' => OrderingGate::for($settings, $this->status, now()),
            'banner' => $settings->bannerVisible ? $settings->bannerText : null,
            'topThree' => $this->topThree->productIds(),
            'favorites' => $this->favorites->forUser(auth()->user()),
        ]);
    }
}
