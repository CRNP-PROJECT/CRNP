@php
    /** @var array<string, \App\Rentals\RentalBooking> $bookings */
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My rentals · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    @include('menu.partials.header')

    <main class="site-main">
        <h1 class="cart-heading">My rentals</h1>

        @if ($bookings === [])
            <section class="empty-state" aria-labelledby="rentals-mine-empty-heading">
                <h2 class="empty-state__title" id="rentals-mine-empty-heading">No rentals yet</h2>
                <p>Browse the rental catalog to book crates, plates, or furniture.</p>
                <a class="primary-button" href="{{ route('rentals.index') }}">Browse rentals</a>
            </section>
        @else
            <ul class="cart-list">
                @foreach ($bookings as $booking)
                    <li class="cart-line">
                        <div class="cart-line__head">
                            <span class="cart-line__name">{{ $booking->code }} · {{ $booking->qty }} unit(s)</span>
                            <span class="cart-line__price">₱{{ number_format($booking->total, 2) }}</span>
                        </div>
                        <p class="site-main__copy">
                            {{ $booking->period->fromString() }} → {{ $booking->period->toString() }}
                            · {{ $booking->status->label() }}
                        </p>
                        <a class="link-button" href="{{ route('rentals.track', ['booking' => $booking->id]) }}">Open tracker</a>
                    </li>
                @endforeach
            </ul>
        @endif
    </main>

    @include('menu.partials.footer')
</body>
</html>
