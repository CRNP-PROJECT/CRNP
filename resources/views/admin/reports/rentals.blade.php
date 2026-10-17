<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rental-booking report · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    @include('admin.partials.nav')

    <main class="site-main">
        <h1>Rental bookings</h1>

        <nav class="report-tabs" aria-label="Reports">
            <a class="report-tabs__link" href="{{ route('admin.reports.sales') }}">Sales</a>
            <a class="report-tabs__link" href="{{ route('admin.reports.payments') }}">Payments</a>
            <a class="report-tabs__link" href="{{ route('admin.reports.reservations') }}">Reservations</a>
            <a class="report-tabs__link" href="{{ route('admin.reports.rentals') }}" aria-current="page">Rental bookings</a>
        </nav>

        <form class="report-filter" method="GET" action="{{ route('admin.reports.rentals') }}">
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

        <section class="dashboard-panel" aria-labelledby="rentals-heading">
            <h2 id="rentals-heading">Rental bookings · {{ $from }} → {{ $to }}</h2>
            <p class="site-main__copy">
                A separate stream from orders — never counted in Top 3 or the
                product trend.
            </p>

            @if ($bookings === [])
                <p class="empty-state">No rental bookings in this range.</p>
            @else
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th scope="col">Code</th>
                            <th scope="col">From</th>
                            <th scope="col">To</th>
                            <th scope="col">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($bookings as $booking)
                            <tr>
                                <td>{{ $booking['code'] ?? '—' }}</td>
                                <td>{{ $booking['dateFrom'] ?? '—' }}</td>
                                <td>{{ $booking['dateTo'] ?? '—' }}</td>
                                <td>₱{{ number_format((float) ($booking['total'] ?? 0), 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    </main>
</body>
</html>
