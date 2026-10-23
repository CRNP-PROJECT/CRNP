<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DashboardFilterRequest;
use App\Settings\SettingsRepository;
use App\Stats\BookingLedger;
use App\Stats\BookingMix;
use App\Stats\DailyTender;
use App\Stats\DashboardMetrics;
use App\Stats\PaymentMix;
use App\Stats\RefundsOwed;
use App\Stats\SalesLedger;
use App\Stats\TenderCounts;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;

/**
 * The admin dashboard (PLAN §4 *Admin* — *Dashboard*).
 *
 * It shows the **Complete setup** banner until the GCash number and the QR image
 * are both configured, the **KPIs** (today's sales and orders), the **monthly
 * payment mix** (GCash / cash / total cards for one business month, stepped with
 * prev/next links) and the **cash-vs-GCash order counts** (how many orders were
 * paid each way, a split-tender walk-in counted on both sides), the **monthly
 * booking mix** (the same three cards for rental
 * bookings — their own revenue stream, read from `rentalBookings` by `dateFrom`),
 * the **7-day trend**, **best-sellers**, **peak hours**, and the **refunds-owed
 * badge** counting the outstanding obligations. Everything is computed
 * server-side from the sales ledger (bounded by `soldAt`); a rental booking
 * (#34) never reaches the product ranking or the trend.
 */
final class DashboardController extends Controller
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly SalesLedger $ledger,
        private readonly BookingLedger $bookings,
        private readonly RefundsOwed $refunds,
    ) {}

    public function __invoke(DashboardFilterRequest $request): View
    {
        $settings = $this->settings->current();
        $now = CarbonImmutable::instance(now('UTC'));

        $sales = array_values(
            $this->ledger->between(
                $now->subDays(DashboardMetrics::WINDOW_DAYS)->toIso8601ZuluString(),
                $now->toIso8601ZuluString(),
            ),
        );

        // The monthly payment mix: the GCash-vs-cash split for one business month.
        $month = $this->businessMonth($request->validated('month'));
        [$monthFrom, $monthTo] = $this->monthRange($month);
        $monthSales = array_values($this->ledger->between($monthFrom, $monthTo));
        $paymentMix = PaymentMix::fromSales($monthSales);

        // The cash-vs-GCash counts for the seven-day window: how many orders
        // were paid each way, alongside the same window's daily bars.
        $tenderCounts = TenderCounts::fromSales($sales);

        // The monthly booking mix: rental bookings are their own revenue stream
        // (never a `sales/{id}` row), so they need their own bounded read —
        // keyed by the indexed `dateFrom`, the same bound the rental report uses.
        [$bookingFrom, $bookingTo] = $this->bookingRange($month);
        $monthBookings = array_values($this->bookings->fromDateRange($bookingFrom, $bookingTo));
        $bookingMix = BookingMix::fromBookings($monthBookings);

        // The per-day tender table: the same two streams folded into one row per
        // business day, each split into orders cash / GCash and bookings cash /
        // GCash. Reads the two sets already in hand, so it costs no extra query.
        $dailyTender = DailyTender::fromSalesAndBookings($monthSales, $monthBookings);

        // The month the cards are titled with, and the two months it steps to.
        $cursor = $this->monthStart($month);
        $previous = $cursor->subMonthNoOverflow();
        $next = $cursor->addMonthNoOverflow();

        $refunds = $this->refunds->all();
        $refundsTotal = 0.0;

        foreach ($refunds as $refund) {
            $refundsTotal += $refund['refundOwed'];
        }

        return view('admin.dashboard', [
            'settings' => $settings,
            'setupComplete' => $settings->hasPaymentBlock(),
            'metrics' => DashboardMetrics::fromSales($sales, $now),
            'refundsCount' => count($refunds),
            'refundsTotal' => round($refundsTotal, 2),
            'paymentMix' => $paymentMix,
            'tenderCounts' => $tenderCounts,
            'bookingMix' => $bookingMix,
            'dailyTender' => $dailyTender,
            'monthLabel' => $cursor->format('F Y'),
            'previousMonth' => $previous->format('Y-m'),
            'previousMonthLabel' => $previous->format('F Y'),
            'nextMonth' => $next->format('Y-m'),
            'nextMonthLabel' => $next->format('F Y'),
        ]);
    }

    /**
     * The requested business month (`Y-m`), defaulting to the current one.
     */
    private function businessMonth(mixed $month): string
    {
        if (is_string($month) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1) {
            return $month;
        }

        return CarbonImmutable::instance(now('UTC'))
            ->setTimezone(BusinessTime::timezone())
            ->format('Y-m');
    }

    /**
     * Midnight on the first day of the business month, in business time.
     */
    private function monthStart(string $month): CarbonImmutable
    {
        return CarbonImmutable::parse($month . '-01', BusinessTime::timezone())->startOfMonth();
    }

    /**
     * The UTC instant range covering the whole business month.
     *
     * @return array{string, string}
     */
    private function monthRange(string $month): array
    {
        $start = $this->monthStart($month);
        $end = $start->endOfMonth();

        return [
            $start->setTimezone('UTC')->toIso8601ZuluString(),
            $end->setTimezone('UTC')->toIso8601ZuluString(),
        ];
    }

    /**
     * The `Y-m-d` date range covering the whole business month — the inclusive
     * `dateFrom` bound the rental-booking tree is indexed and queried by.
     *
     * @return array{string, string}
     */
    private function bookingRange(string $month): array
    {
        $start = $this->monthStart($month);

        return [$start->toDateString(), $start->endOfMonth()->toDateString()];
    }
}
