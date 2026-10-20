<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Activity\CashierHistory;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * The admin **Cashier history** page (CONTEXT — *Activity* / *Handled by*).
 *
 * It answers *which cashier accepted or rejected which customer* — orders and
 * rental bookings alike — as *time · cashier · decision · subject · code ·
 * detail*. Every source read is bounded (a `limitToLast` on an indexed field),
 * so the page costs a fixed number of rows regardless of how long the diner has
 * traded.
 */
final class CashierHistoryController extends Controller
{
    public function __construct(private readonly CashierHistory $history) {}

    public function __invoke(): View
    {
        return view('admin.cashier-history', [
            'entries' => $this->history->latest(),
        ]);
    }
}
