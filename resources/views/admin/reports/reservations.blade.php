<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reservation report · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    @include('admin.partials.nav')

    <main class="site-main">
        <h1>Reservation report</h1>

        <nav class="report-tabs" aria-label="Reports">
            <a class="report-tabs__link" href="{{ route('admin.reports.sales') }}">Sales</a>
            <a class="report-tabs__link" href="{{ route('admin.reports.payments') }}">Payments</a>
            <a class="report-tabs__link" href="{{ route('admin.reports.reservations') }}" aria-current="page">Reservations</a>
            <a class="report-tabs__link" href="{{ route('admin.reports.rentals') }}">Rental bookings</a>
        </nav>

        <form class="report-filter" method="GET" action="{{ route('admin.reports.reservations') }}">
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

        <section class="dashboard-panel" aria-labelledby="reservations-heading">
            <h2 id="reservations-heading">Reservations · {{ $from }} → {{ $to }}</h2>

            @if ($reservations === [])
                <p class="empty-state">No reservations in this range.</p>
            @else
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th scope="col">Date</th>
                            <th scope="col">Time</th>
                            <th scope="col">Name</th>
                            <th scope="col">Phone</th>
                            <th scope="col">Type</th>
                            <th scope="col">Party</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($reservations as $reservation)
                            <tr>
                                <td>{{ $reservation->date }}</td>
                                <td>{{ $reservation->time }}</td>
                                <td>{{ $reservation->name }}</td>
                                <td>{{ $reservation->phone }}</td>
                                <td>{{ $reservation->type->label() }}</td>
                                <td>{{ $reservation->party }}</td>
                                <td>{{ $reservation->status->label() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    </main>
</body>
</html>
