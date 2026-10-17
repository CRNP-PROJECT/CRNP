<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Cache\AvailabilityCache;
use App\Http\Controllers\Controller;
use App\Reservations\Occupancy;
use Illuminate\Contracts\View\View;

/**
 * The public read-only **occupancy view** (PLAN §4 *Reservations*).
 *
 * Seats taken vs capacity per day and time slot for the week ahead, confirmed
 * bookings only, server-aggregated, aggregate only — no names, no phones, and
 * no booking form. It answers "is date X free?" without a call and books
 * nothing. The computed result is served from the availability cache (60 s),
 * which reservation and capacity writes evict.
 */
final class OccupancyController extends Controller
{
    public function __construct(
        private readonly Occupancy $occupancy,
        private readonly AvailabilityCache $availability,
    ) {}

    public function __invoke(): View
    {
        $days = $this->availability->remember(
            fn(): array => $this->occupancy->week(now()),
        );

        return view('occupancy', [
            'days' => $days,
        ]);
    }
}
