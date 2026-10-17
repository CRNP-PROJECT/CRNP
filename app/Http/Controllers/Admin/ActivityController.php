<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Activity\ActivityFeed;
use App\Http\Controllers\Controller;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;

/**
 * The admin **Activity** feed (PLAN §4 *Admin* — *Reports*; CONTEXT —
 * *Activity*).
 *
 * It shows *time · who · action · order code*, **latest N and bounded**, plus the
 * **per-cashier daily totals** grouped by `handledBy` for the business day. The
 * feed reads the sales ledger and the audit records through bounded
 * `limitToLast` queries, so it never scans a whole tree.
 */
final class ActivityController extends Controller
{
    public function __construct(private readonly ActivityFeed $feed) {}

    public function __invoke(): View
    {
        $today = CarbonImmutable::instance(now('UTC'))->setTimezone(BusinessTime::timezone())->toDateString();

        return view('admin.activity', [
            'entries' => $this->feed->latest(),
            'totals' => $this->feed->dailyTotals($today),
            'date' => $today,
        ]);
    }
}
