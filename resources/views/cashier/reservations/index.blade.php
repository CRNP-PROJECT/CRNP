@php
    /** @var array<string, array<string, \App\Reservations\Reservation>> $grouped */
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reservations · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    @include('cashier.partials.register-header')

    <main class="site-main">
        <div class="reservation-heading">
            <div>
                <h1>Reservations</h1>
                <p class="site-main__copy">
                    {{ $from }} → {{ $to }} · dine-in, function room, catering.
                    The server checks every booking for a date-time conflict or a
                    duplicate before it is confirmed.
                </p>
            </div>
            {{-- The one primary action on this view: start a new entry. --}}
            <a class="primary-button" href="{{ route('cashier.reservations.create') }}">New reservation</a>
        </div>

        @if (session('status'))
            <p class="notice" role="status">{{ session('status') }}</p>
        @endif

        @error('reservation')
            <p class="notice notice--error" role="alert">{{ $message }}</p>
        @enderror

        @if ($grouped === [])
            <section class="empty-state" aria-labelledby="reservations-empty-heading">
                <h2 class="empty-state__title" id="reservations-empty-heading">No reservations this week</h2>
                <p>The week ahead is clear — enter a booking with New reservation.</p>
            </section>
        @else
            <div class="reservation-list">
                @foreach ($grouped as $date => $reservations)
                    <section class="reservation-day" aria-labelledby="day-{{ $date }}">
                        <h2 class="reservation-day__title" id="day-{{ $date }}">{{ $date }}</h2>
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th scope="col">Time</th>
                                    <th scope="col">Guest</th>
                                    <th scope="col">Type</th>
                                    <th scope="col">Party</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($reservations as $reservation)
                                    <tr>
                                        <td>{{ $reservation->time }}</td>
                                        <td>
                                            {{ $reservation->name }}
                                            <span class="reservation-phone">{{ $reservation->phone }}</span>
                                        </td>
                                        <td>{{ $reservation->type->label() }}</td>
                                        <td>{{ $reservation->party }}</td>
                                        <td>
                                            <span class="status-tag status-tag--{{ $reservation->status->value }}">
                                                {{ $reservation->status->label() }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="inline-form">
                                                @if ($reservation->status === \App\Reservations\ReservationStatus::Pending)
                                                    <form method="POST" action="{{ route('cashier.reservations.confirm', $reservation->id) }}">
                                                        @csrf
                                                        <button type="submit" class="primary-button">Confirm</button>
                                                    </form>
                                                @endif
                                                @if ($reservation->status !== \App\Reservations\ReservationStatus::Cancelled)
                                                    <form method="POST" action="{{ route('cashier.reservations.cancel', $reservation->id) }}">
                                                        @csrf
                                                        <button type="submit" class="toggle-button">Cancel</button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </section>
                @endforeach
            </div>
        @endif
    </main>
</body>
</html>
