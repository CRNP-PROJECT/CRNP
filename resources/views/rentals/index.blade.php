@php
    /** @var list<\App\Rentals\RentalCatalogEntry> $entries */
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rentals · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    @include('menu.partials.header')

    <main class="site-main">
        <section class="rentals-hero" aria-labelledby="rentals-heading">
            <div class="rentals-hero__intro">
                <h1 id="rentals-heading">Rentals</h1>
                <p class="site-main__copy">
                    Crates, plates, and furniture for hire. Price is per day;
                    availability computes itself from confirmed bookings — no
                    calling ahead to check. Prepay in full through GCash when you
                    book.
                </p>
            </div>
            @include('menu.partials.store-badge')
            @include('menu.partials.order-gate')
        </section>

        @if ($entries === [])
            <section class="empty-state" aria-labelledby="rentals-empty-heading">
                <h2 class="empty-state__title" id="rentals-empty-heading">No rental items yet</h2>
                <p>The owner adds rentable stock from the back office.</p>
            </section>
        @else
            <div class="rental-grid">
                @foreach ($entries as $entry)
                    <article class="rental-card" aria-labelledby="rental-{{ $entry->item->id }}">
                        @if ($entry->item->imageBase64 !== '')
                            <img class="rental-card__image" src="data:image/jpeg;base64,{{ $entry->item->imageBase64 }}" alt="">
                        @endif
                        <div class="rental-card__body">
                            <h2 class="rental-card__name" id="rental-{{ $entry->item->id }}">{{ $entry->item->name }}</h2>
                            <p class="rental-card__price">
                                <span class="rental-card__rate">₱{{ number_format($entry->item->pricePerDay, 2) }}</span>
                                <span class="rental-card__unit">per day</span>
                            </p>
                        </div>

                        <div class="rental-days" aria-label="Availability for {{ $entry->item->name }}">
                            @foreach ($entry->availability as $day)
                                <div class="rental-day {{ $day->isFull() ? 'rental-day--full' : '' }}" data-date="{{ $day->date }}">
                                    <span class="rental-day__date">{{ $day->date }}</span>
                                    <span class="rental-day__count">{{ $day->seats() }} available</span>
                                </div>
                            @endforeach
                        </div>

                        <a class="primary-button" href="{{ route('rentals.show', ['item' => $entry->item->id]) }}">Book {{ $entry->item->name }}</a>
                    </article>
                @endforeach
            </div>
        @endif
    </main>

    @include('menu.partials.footer')
</body>
</html>
