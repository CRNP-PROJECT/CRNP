@php
    /** @var list<\App\Reservations\OccupancyDay> $days */
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Availability · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    @include('menu.partials.header')

    <main class="site-main">
        <section class="occupancy-hero" aria-labelledby="occupancy-heading">
            <h1 id="occupancy-heading">Availability this week</h1>
            <p class="site-main__copy">
                Seats taken vs capacity per time slot, for the week ahead.
                Confirmed bookings only — this is an aggregate view, so it shows
                no names and books nothing. Call the diner to reserve.
            </p>
        </section>

        <div class="occupancy-week">
            @foreach ($days as $day)
                <section class="occupancy-day" aria-labelledby="occupancy-{{ $day->date }}">
                    <h2 class="occupancy-day__title" id="occupancy-{{ $day->date }}">{{ $day->date }}</h2>
                    @if ($day->slots === [])
                        <p class="occupancy-day__empty">No confirmed bookings yet.</p>
                    @else
                        @foreach ($day->slots as $slot)
                            <div class="occupancy-slot">
                                <h3 class="occupancy-slot__time">{{ $slot->slot->label() }}</h3>
                                <ul class="occupancy-slot__counts">
                                    @foreach ($slot->counts as $count)
                                        <li class="occupancy-count {{ $count->isFull() ? 'occupancy-count--full' : '' }}">
                                            <span class="occupancy-count__area">{{ $count->area->label() }}</span>
                                            <span class="occupancy-count__seats">{{ $count->seats() }} seats</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    @endif
                </section>
            @endforeach
        </div>
    </main>

    @include('menu.partials.footer')
</body>
</html>
