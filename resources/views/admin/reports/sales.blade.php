<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sales report · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    @include('admin.partials.nav')

    <main class="site-main">
        <h1>Sales report</h1>

        @if (session('status'))
            <p class="notice" role="status">{{ session('status') }}</p>
        @endif

        <nav class="report-tabs" aria-label="Reports">
            <a class="report-tabs__link" href="{{ route('admin.reports.sales') }}" aria-current="page">Sales</a>
            <a class="report-tabs__link" href="{{ route('admin.reports.payments') }}">Payments</a>
            <a class="report-tabs__link" href="{{ route('admin.reports.reservations') }}">Reservations</a>
            <a class="report-tabs__link" href="{{ route('admin.reports.rentals') }}">Rental bookings</a>
        </nav>

        {{-- One-click date filter: the report computes its own totals. --}}
        <form class="report-filter" method="GET" action="{{ route('admin.reports.sales') }}">
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

        <section class="dashboard-panel" aria-labelledby="sales-heading">
            <h2 id="sales-heading">Sales · {{ $from }} → {{ $to }}</h2>

            @if ($sales === [])
                <p class="empty-state">No sales in this range.</p>
            @else
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th scope="col">Code</th>
                            <th scope="col">Type</th>
                            <th scope="col">Sold at</th>
                            <th scope="col">Handled by</th>
                            <th scope="col">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sales as $sale)
                            <tr>
                                <td>{{ $sale['code'] ?? '—' }}</td>
                                <td>{{ $sale['type'] ?? '—' }}</td>
                                <td>{{ isset($sale['soldAt']) ? \Illuminate\Support\Carbon::parse($sale['soldAt'])->setTimezone('Asia/Manila')->format('Y-m-d H:i') : '—' }}</td>
                                <td>{{ $sale['handledBy']['name'] ?? '—' }}</td>
                                <td>₱{{ number_format((float) ($sale['total'] ?? 0), 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th scope="row" colspan="4">Total</th>
                            <td>₱{{ number_format($total, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            @endif
        </section>

        <section class="dashboard-panel" aria-labelledby="refunds-heading">
            <h2 id="refunds-heading">Refunds owed</h2>
            <p class="site-main__copy">
                The obligation booked when a proof was rejected — the claimed amount,
                cleared only here.
            </p>

            @if ($refunds === [])
                <p class="empty-state">No refunds owed — nothing outstanding.</p>
            @else
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th scope="col">Order code</th>
                            <th scope="col">Claimed</th>
                            <th scope="col">Order total</th>
                            <th scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($refunds as $refund)
                            <tr>
                                <td>{{ $refund['code'] }}</td>
                                <td>₱{{ number_format($refund['refundOwed'], 2) }}</td>
                                <td>₱{{ number_format($refund['orderTotal'], 2) }}</td>
                                <td>
                                    <form method="POST" action="{{ route('admin.reports.refunds.mark', ['order' => $refund['orderId']]) }}" class="inline-form">
                                        @csrf
                                        <button type="submit" class="link-button">Mark refunded</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th scope="row" colspan="3">Outstanding</th>
                            <td>₱{{ number_format($refundsTotal, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            @endif
        </section>
    </main>
</body>
</html>
