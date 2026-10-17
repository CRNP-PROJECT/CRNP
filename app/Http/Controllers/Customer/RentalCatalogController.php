<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Customer\OrderingGate;
use App\Http\Controllers\Controller;
use App\Rentals\RentalCatalog;
use App\Settings\SettingsRepository;
use App\Settings\StoreStatus;
use Illuminate\Contracts\View\View;

/**
 * The public **rental catalog** (PLAN §4 *Rentals*).
 *
 * Read-only on `/rentals`: each item's image, price per day, and computed
 * per-date availability (stock vs overlapping confirmed bookings). It respects
 * the same open/closed badge as place-order — the #22 {@see OrderingGate} — so
 * booking follows the diner's hours, and it books nothing itself.
 *
 * The computed catalog is served from the shared availability cache (#33), so a
 * page view never re-reads the booking tree.
 */
final class RentalCatalogController extends Controller
{
    public function __construct(
        private readonly RentalCatalog $catalog,
        private readonly SettingsRepository $settings,
        private readonly StoreStatus $status,
    ) {}

    public function __invoke(): View
    {
        $settings = $this->settings->current();

        return view('rentals.index', [
            'entries' => $this->catalog->entries(now()),
            'gate' => OrderingGate::for($settings, $this->status, now()),
        ]);
    }
}
