<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReportFilterRequest;
use App\Orders\HandledBy;
use App\Orders\Order;
use App\Orders\Orders;
use App\Reservations\Reservations;
use App\Rtdb\Contracts\RtdbClient;
use App\Rtdb\RtdbQuery;
use App\Staff\Staff;
use App\Staff\StaffDirectory;
use App\Stats\Actions\MarkRefunded;
use App\Stats\DailySales;
use App\Stats\PaymentMix;
use App\Stats\RefundsOwed;
use App\Stats\SalesLedger;
use App\Stats\ServiceMix;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The admin's reports (PLAN §4 *Admin* — *Reports*).
 *
 * Three reports — sales, reservations, and rental bookings — each **filtered by
 * date** through one-click ranges. Every read is bounded by an indexed date
 * field: `sales.soldAt`, `reservations.date`, and `rentalBookings.dateFrom`. The
 * sales report also carries the **refunds-owed column** with the admin-only
 * **Mark refunded** action; a cashier is refused at the route guard.
 *
 * A fourth view, **payments**, splits the same sales ledger into **GCash vs cash
 * (pay at the counter)** totals over the range — an online order counting as
 * GCash in full.
 *
 * The rental-booking shape is owned by #34. Until it lands, this report reads the
 * `rentalBookings` tree defensively: a missing tree renders an empty state,
 * never an error.
 */
final class ReportsController extends Controller
{
    public function __construct(
        private readonly SalesLedger $ledger,
        private readonly RefundsOwed $refunds,
        private readonly Reservations $reservations,
        private readonly Orders $orders,
        private readonly RtdbClient $client,
        private readonly StaffDirectory $directory,
    ) {}

    public function sales(ReportFilterRequest $request): View
    {
        [$from, $to] = $this->range($request->validated('from'), $request->validated('to'));
        [$fromIso, $toIso] = $this->instantRange($from, $to);

        $rows = array_values($this->ledger->between($fromIso, $toIso));

        $total = 0.0;

        foreach ($rows as $row) {
            $amount = $row['total'] ?? 0;
            $total += is_numeric($amount) ? (float) $amount : 0.0;
        }

        $refunds = $this->refunds->all();
        $refundsTotal = 0.0;

        foreach ($refunds as $refund) {
            $refundsTotal += $refund['refundOwed'];
        }

        // Every order placed in the range, across all statuses — a pending or
        // rejected order never reaches the sales ledger, so the report cannot
        // show it from `sales` alone. Bounded by the indexed `placedAt`.
        $orders = array_values($this->orders->placedBetween($fromIso, $toIso));

        usort($orders, static fn(Order $a, Order $b): int => strcmp((string) $b->placedAt, (string) $a->placedAt));

        $ordersTotal = 0.0;

        foreach ($orders as $order) {
            $ordersTotal += $order->total;
        }

        return view('admin.reports.sales', [
            'from' => $from,
            'to' => $to,
            'sales' => $rows,
            'total' => round($total, 2),
            'refunds' => $refunds,
            'refundsTotal' => round($refundsTotal, 2),
            'timeline' => DailySales::fromSalesInRange($rows, $from, $to),
            'mix' => PaymentMix::fromSales($rows),
            'orders' => $orders,
            'ordersTotal' => round($ordersTotal, 2),
        ]);
    }

    public function payments(ReportFilterRequest $request): View
    {
        [$from, $to] = $this->range($request->validated('from'), $request->validated('to'));
        [$fromIso, $toIso] = $this->instantRange($from, $to);

        $rows = array_values($this->ledger->between($fromIso, $toIso));

        return view('admin.reports.payments', [
            'from' => $from,
            'to' => $to,
            'mix' => PaymentMix::fromSales($rows),
            'serviceMix' => ServiceMix::fromSales($rows),
        ]);
    }

    public function reservations(ReportFilterRequest $request): View
    {
        [$from, $to] = $this->range($request->validated('from'), $request->validated('to'));

        return view('admin.reports.reservations', [
            'from' => $from,
            'to' => $to,
            'reservations' => $this->reservations->between($from, $to),
        ]);
    }

    public function rentals(ReportFilterRequest $request): View
    {
        [$from, $to] = $this->range($request->validated('from'), $request->validated('to'));

        return view('admin.reports.rentals', [
            'from' => $from,
            'to' => $to,
            'bookings' => $this->rentalBookings($from, $to),
        ]);
    }

    public function markRefunded(string $order, MarkRefunded $markRefunded): RedirectResponse
    {
        if ($this->client->get("orders/{$order}") === null) {
            throw new NotFoundHttpException("No order with id [{$order}].");
        }

        $markRefunded($order, $this->admin());

        return redirect()
            ->back()
            ->with('status', 'Refund marked as refunded.');
    }

    /**
     * The rental bookings in the date range, read defensively by `dateFrom`.
     *
     * @return array<string, array<string, mixed>>
     */
    private function rentalBookings(string $from, string $to): array
    {
        $rows = $this->client->query(
            'rentalBookings',
            RtdbQuery::orderBy('dateFrom')->range($from, $to),
        );

        return $rows;
    }

    /**
     * @return array{string, string}
     */
    private function range(mixed $from, mixed $to): array
    {
        $today = CarbonImmutable::instance(now('UTC'))->setTimezone(BusinessTime::timezone())->toDateString();

        $start = is_string($from) && $from !== '' ? $from : $today;
        $end = is_string($to) && $to !== '' ? $to : $start;

        return [$start, $end];
    }

    /**
     * The UTC instant bounds of a Manila date range — from 00:00 on `$from`
     * through 23:59:59 on `$to`, in the diner's timezone. The reports filter
     * on plain `Y-m-d` dates, so a "6 Oct" report must mean the whole Manila
     * 6th, not the UTC day that straddles it.
     *
     * @return array{string, string}
     */
    private function instantRange(string $from, string $to): array
    {
        $timezone = BusinessTime::timezone();

        return [
            CarbonImmutable::parse($from, $timezone)->startOfDay()->toIso8601ZuluString(),
            CarbonImmutable::parse($to, $timezone)->endOfDay()->toIso8601ZuluString(),
        ];
    }

    private function admin(): HandledBy
    {
        $staff = $this->directory->forUser(auth()->user());

        if (! $staff instanceof Staff) {
            abort(403, 'This area is for admin accounts only.');
        }

        return HandledBy::fromStaff($staff);
    }
}
