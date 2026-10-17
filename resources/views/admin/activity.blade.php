<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Activity · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    @include('admin.partials.nav')

    <main class="site-main">
        <h1>Activity</h1>
        <p class="site-main__copy">
            Who did what — time · who · action · order code. The feed is bounded to
            the latest {{ \App\Activity\ActivityFeed::LATEST }} entries.
        </p>

        <section class="dashboard-panel" aria-labelledby="totals-heading">
            <h2 id="totals-heading">Per-cashier totals · {{ $date }}</h2>

            @if ($totals === [])
                <p class="empty-state">No sales handled today.</p>
            @else
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th scope="col">Cashier</th>
                            <th scope="col">Orders</th>
                            <th scope="col">Sales</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($totals as $row)
                            <tr>
                                <td>{{ $row['name'] }}</td>
                                <td>{{ $row['orders'] }}</td>
                                <td>₱{{ number_format($row['total'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>

        <section class="dashboard-panel" aria-labelledby="feed-heading">
            <h2 id="feed-heading">Latest activity</h2>

            @if ($entries === [])
                <p class="empty-state">No activity yet.</p>
            @else
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th scope="col">Time</th>
                            <th scope="col">Who</th>
                            <th scope="col">Action</th>
                            <th scope="col">Order code</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($entries as $entry)
                            <tr>
                                <td>{{ \Illuminate\Support\Carbon::parse($entry->at)->setTimezone('Asia/Manila')->format('Y-m-d H:i') }}</td>
                                <td>{{ $entry->who }}</td>
                                <td>{{ $entry->action }}</td>
                                <td>{{ $entry->code }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    </main>
</body>
</html>
