<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    @include('admin.partials.nav')

    <main class="site-main">
        <h1>Dashboard</h1>

        @unless ($setupComplete)
            <p class="setup-banner" role="alert">
                <strong>Complete setup.</strong>
                Ordering stays closed until both the GCash number and the official
                QR image are configured.
                <a href="{{ route('admin.settings.edit') }}">Open settings</a>.
            </p>
        @endunless

        {{-- The refunds-owed badge: money owed, the one standing obligation. --}}
        <section class="badge-row" aria-label="Standing badges">
            <div class="badge-card badge-card--refunds">
                <span class="badge-card__label">Refunds owed</span>
                <span class="badge-card__value">₱{{ number_format($refundsTotal, 2) }}</span>
                <span class="badge-card__detail">{{ $refundsCount }} outstanding</span>
            </div>
        </section>

        {{-- KPIs for today. --}}
        <section class="kpi-row" aria-label="Key figures">
            <div class="kpi">
                <span class="kpi__label">Sales today</span>
                <span class="kpi__value">₱{{ number_format($metrics->salesToday, 2) }}</span>
            </div>
            <div class="kpi">
                <span class="kpi__label">Orders today</span>
                <span class="kpi__value">{{ $metrics->ordersToday }}</span>
            </div>
            <div class="kpi">
                <span class="kpi__label">Sales · 7 days</span>
                <span class="kpi__value">₱{{ number_format($metrics->salesWindow, 2) }}</span>
            </div>
            <div class="kpi">
                <span class="kpi__label">Orders · 7 days</span>
                <span class="kpi__value">{{ $metrics->ordersWindow }}</span>
            </div>
        </section>

        {{-- Monthly payment mix: GCash, cash and total as a third KPI row, one
             business month at a time. --}}
        <nav class="month-nav" aria-label="Payment month">
            <a class="month-nav__link" href="{{ route('admin.dashboard', ['month' => $previousMonth]) }}" rel="prev">
                ‹ {{ $previousMonthLabel }}
            </a>
            <span class="month-nav__current" aria-current="page">{{ $monthLabel }}</span>
            <a class="month-nav__link" href="{{ route('admin.dashboard', ['month' => $nextMonth]) }}" rel="next">
                {{ $nextMonthLabel }} ›
            </a>
        </nav>

        <section class="kpi-row" aria-label="Payments for {{ $monthLabel }}">
            <div class="kpi">
                <span class="kpi__label">GCash · {{ $monthLabel }}</span>
                <span class="kpi__value">₱{{ number_format($paymentMix->gcash, 2) }}</span>
            </div>
            <div class="kpi">
                <span class="kpi__label">Cash · pay at the counter · {{ $monthLabel }}</span>
                <span class="kpi__value">₱{{ number_format($paymentMix->cash, 2) }}</span>
            </div>
            <div class="kpi">
                <span class="kpi__label">Total · {{ $monthLabel }}</span>
                <span class="kpi__value">₱{{ number_format($paymentMix->total(), 2) }}</span>
            </div>
        </section>

        @if ($paymentMix->orders === 0)
            <p class="empty-state">No payments in {{ $monthLabel }}.</p>
        @else
            <p class="site-main__copy">
                {{ $paymentMix->orders }} {{ \Illuminate\Support\Str::plural('order', $paymentMix->orders) }}
                in {{ $monthLabel }} — an online pickup counts as GCash in full, a
                walk-in counts each leg of its cash / GCash split.
            </p>
        @endif

        {{-- Monthly booking mix: the same three cards for rental bookings —
             their own revenue stream, paid bookings only (confirmed, handed
             over, or returned). --}}
        <section class="kpi-row" aria-label="Bookings for {{ $monthLabel }}">
            <div class="kpi">
                <span class="kpi__label">Booking GCash · {{ $monthLabel }}</span>
                <span class="kpi__value">₱{{ number_format($bookingMix->gcash, 2) }}</span>
            </div>
            <div class="kpi">
                <span class="kpi__label">Booking cash · {{ $monthLabel }}</span>
                <span class="kpi__value">₱{{ number_format($bookingMix->cash, 2) }}</span>
            </div>
            <div class="kpi">
                <span class="kpi__label">Booking total · {{ $monthLabel }}</span>
                <span class="kpi__value">₱{{ number_format($bookingMix->total(), 2) }}</span>
            </div>
        </section>

        @if ($bookingMix->bookings === 0)
            <p class="empty-state">No paid bookings in {{ $monthLabel }}.</p>
        @else
            <p class="site-main__copy">
                {{ $bookingMix->bookings }} paid {{ \Illuminate\Support\Str::plural('booking', $bookingMix->bookings) }}
                in {{ $monthLabel }} — a rental booking is prepaid through the same
                GCash gate as an order, so a booking with no tender legs counts as
                GCash in full.
            </p>
        @endif

        {{-- Per-day collections: each business day of the month, split into
             orders cash / GCash and bookings cash / GCash. --}}
        <section class="dashboard-panel" aria-labelledby="daily-tender-heading">
            <h2 id="daily-tender-heading">Daily collections · {{ $monthLabel }}</h2>

            @if ($dailyTender->days === [])
                <p class="empty-state">No collections in {{ $monthLabel }}.</p>
            @else
                @php($dailyTotals = $dailyTender->totals())

                <table class="admin-table">
                    <thead>
                        <tr>
                            <th scope="col">Date</th>
                            <th scope="col">Orders · GCash</th>
                            <th scope="col">Orders · Cash</th>
                            <th scope="col">Bookings · GCash</th>
                            <th scope="col">Bookings · Cash</th>
                            <th scope="col">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($dailyTender->days as $day)
                            <tr>
                                <td>{{ \Illuminate\Support\Carbon::parse($day['date'])->format('D j M') }}</td>
                                <td>₱{{ number_format($day['ordersGcash'], 2) }}</td>
                                <td>₱{{ number_format($day['ordersCash'], 2) }}</td>
                                <td>₱{{ number_format($day['bookingsGcash'], 2) }}</td>
                                <td>₱{{ number_format($day['bookingsCash'], 2) }}</td>
                                <td>₱{{ number_format($day['ordersGcash'] + $day['ordersCash'] + $day['bookingsGcash'] + $day['bookingsCash'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th scope="row">Total</th>
                            <td>₱{{ number_format($dailyTotals['ordersGcash'], 2) }}</td>
                            <td>₱{{ number_format($dailyTotals['ordersCash'], 2) }}</td>
                            <td>₱{{ number_format($dailyTotals['bookingsGcash'], 2) }}</td>
                            <td>₱{{ number_format($dailyTotals['bookingsCash'], 2) }}</td>
                            <td>₱{{ number_format($dailyTotals['total'], 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            @endif
        </section>

        <section class="dashboard-panel" aria-labelledby="trend-heading">
            <h2 id="trend-heading">7-day trend</h2>

            @php($maxTrend = max(array_map(fn($day) => $day['total'], $metrics->trend)) ?: 1.0)

            <ul class="trend">
                @foreach ($metrics->trend as $day)
                    <li class="trend__day">
                        <span class="trend__bar-wrap">
                            <span
                                class="trend__bar"
                                style="height: {{ round(($day['total'] / $maxTrend) * 100, 1) }}%"
                            ></span>
                        </span>
                        <span class="trend__value">₱{{ number_format($day['total'], 0) }}</span>
                        <span class="trend__date">{{ \Illuminate\Support\Carbon::parse($day['date'])->format('D j M') }}</span>
                    </li>
                @endforeach
            </ul>
        </section>

        <div class="dashboard-columns">
            <section class="dashboard-panel" aria-labelledby="best-sellers-heading">
                <h2 id="best-sellers-heading">Best sellers</h2>

                @if ($metrics->bestSellers === [])
                    <p class="empty-state">No sales in the last seven days — the ranking fills itself as orders settle.</p>
                @else
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th scope="col">Product</th>
                                <th scope="col">Units</th>
                                <th scope="col">Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($metrics->bestSellers as $row)
                                <tr>
                                    <td>{{ $row['name'] }}</td>
                                    <td>{{ $row['units'] }}</td>
                                    <td>₱{{ number_format($row['revenue'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>

            <section class="dashboard-panel" aria-labelledby="peak-hours-heading">
                <h2 id="peak-hours-heading">Peak hours</h2>

                @if ($metrics->peakHours === [])
                    <p class="empty-state">No orders in the last seven days.</p>
                @else
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th scope="col">Hour</th>
                                <th scope="col">Orders</th>
                                <th scope="col">Sales</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($metrics->peakHours as $row)
                                <tr>
                                    <td>{{ $row['hour'] }}</td>
                                    <td>{{ $row['orders'] }}</td>
                                    <td>₱{{ number_format($row['total'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>
        </div>
    </main>
</body>
</html>
