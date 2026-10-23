<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payments report · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    @include('admin.partials.nav')

    <main class="site-main">
        <h1>Payments report</h1>

        @if (session('status'))
            <p class="notice" role="status">{{ session('status') }}</p>
        @endif

        <nav class="report-tabs" aria-label="Reports">
            <a class="report-tabs__link" href="{{ route('admin.reports.sales') }}">Sales</a>
            <a class="report-tabs__link" href="{{ route('admin.reports.payments') }}" aria-current="page">Payments</a>
            <a class="report-tabs__link" href="{{ route('admin.reports.reservations') }}">Reservations</a>
            <a class="report-tabs__link" href="{{ route('admin.reports.rentals') }}">Rental bookings</a>
        </nav>

        {{-- One-click date filter: the report computes its own totals. --}}
        <form class="report-filter" method="GET" action="{{ route('admin.reports.payments') }}">
            <label class="field">
                <span class="field__label">From</span>
                <input class="field__input" type="date" name="from" value="{{ $from }}">
            </label>
            <label class="field">
                <span class="field__label">To</span>
                <input class="field__input" type="date" name="to" value="{{ $to }}">
            </label>
            <button type="submit" class="primary-button">Apply</button>
        </form>

        <section class="dashboard-panel" aria-labelledby="payments-heading">
            <h2 id="payments-heading">Payments · {{ $from }} → {{ $to }}</h2>
            <p class="site-main__copy">
                How customers paid in this range. An online pickup is prepaid through
                the GCash gate, so its whole total counts as GCash; a walk-in counts
                each tender leg of its cash / GCash split.
            </p>

            @if ($mix->orders === 0)
                <p class="empty-state">No payments in this range. Widen the dates above to see the chart.</p>
            @else
                @include('admin.reports.partials.tender-donut', ['mix' => $mix])

                <table class="admin-table">
                    <thead>
                        <tr>
                            <th scope="col">Method</th>
                            <th scope="col">Collected</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>GCash</td>
                            <td>₱{{ number_format($mix->gcash, 2) }}</td>
                        </tr>
                        <tr>
                            <td>Cash · pay at the counter</td>
                            <td>₱{{ number_format($mix->cash, 2) }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th scope="row">Total</th>
                            <td>₱{{ number_format($mix->total(), 2) }}</td>
                        </tr>
                    </tfoot>
                </table>

                <p class="site-main__copy">{{ $mix->orders }} {{ \Illuminate\Support\Str::plural('order', $mix->orders) }} in this range.</p>
            @endif
        </section>

        <section class="dashboard-panel" aria-labelledby="service-heading">
            <h2 id="service-heading">Service · {{ $from }} → {{ $to }}</h2>
            <p class="site-main__copy">
                How orders were served in this range. A backfilled sale recorded
                before service type was tracked counts as unspecified.
            </p>

            @if ($serviceMix->orders === 0)
                <p class="empty-state">No orders in this range.</p>
            @else
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th scope="col">Service</th>
                            <th scope="col">Orders</th>
                            <th scope="col">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Dine-in</td>
                            <td>{{ $serviceMix->dineInOrders }}</td>
                            <td>₱{{ number_format($serviceMix->dineIn, 2) }}</td>
                        </tr>
                        <tr>
                            <td>Take-out</td>
                            <td>{{ $serviceMix->takeOutOrders }}</td>
                            <td>₱{{ number_format($serviceMix->takeOut, 2) }}</td>
                        </tr>
                        @if ($serviceMix->unspecified > 0)
                            <tr>
                                <td>Unspecified</td>
                                <td>{{ $serviceMix->orders - $serviceMix->dineInOrders - $serviceMix->takeOutOrders }}</td>
                                <td>₱{{ number_format($serviceMix->unspecified, 2) }}</td>
                            </tr>
                        @endif
                    </tbody>
                    <tfoot>
                        <tr>
                            <th scope="row">Total</th>
                            <td>{{ $serviceMix->orders }}</td>
                            <td>₱{{ number_format($serviceMix->total(), 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            @endif
        </section>
    </main>
</body>
</html>
