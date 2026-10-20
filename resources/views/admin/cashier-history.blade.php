<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cashier history · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    @include('admin.partials.nav')

    <main class="site-main">
        <h1>Cashier history</h1>
        <p class="site-main__copy">
            Who accepted or rejected which customer — time · cashier · decision ·
            subject · code. Orders and rental bookings, bounded to the latest
            {{ \App\Activity\CashierHistory::LATEST }} decisions per source.
        </p>

        <section class="dashboard-panel" aria-labelledby="decisions-heading">
            <h2 id="decisions-heading">Latest decisions</h2>

            @if ($entries === [])
                <p class="empty-state">No cashier decisions yet — an accept or reject tap lands here.</p>
            @else
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th scope="col">Time</th>
                            <th scope="col">Cashier</th>
                            <th scope="col">Decision</th>
                            <th scope="col">Subject</th>
                            <th scope="col">Code</th>
                            <th scope="col">Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($entries as $entry)
                            <tr>
                                <td>{{ $entry->at === '' ? '—' : \Illuminate\Support\Carbon::parse($entry->at)->setTimezone('Asia/Manila')->format('Y-m-d H:i') }}</td>
                                <td>{{ $entry->who }}</td>
                                <td>
                                    <span class="status-tag {{ $entry->decision === 'Accepted' ? 'status-tag--confirmed' : 'status-tag--pending' }}">
                                        {{ $entry->decision }}
                                    </span>
                                </td>
                                <td>{{ $entry->subject }}</td>
                                <td>{{ $entry->code }}</td>
                                <td>{{ $entry->detail }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    </main>
</body>
</html>
