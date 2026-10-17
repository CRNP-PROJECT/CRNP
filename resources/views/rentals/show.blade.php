@php
    /** @var \App\Rentals\RentalCatalogEntry $entry */
    $item = $entry->item;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rent {{ $item->name }} · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    @include('menu.partials.header')

    <main class="site-main">
        <p class="breadcrumb"><a href="{{ route('rentals.index') }}">Rentals</a></p>

        <section class="rental-detail" aria-labelledby="rental-detail-heading">
            @if ($item->imageBase64 !== '')
                <img class="rental-detail__image" src="data:image/jpeg;base64,{{ $item->imageBase64 }}" alt="">
            @endif

            <div class="rental-detail__body">
                <h1 id="rental-detail-heading">{{ $item->name }}</h1>
                <p class="rental-card__price">
                    <span class="rental-card__rate">₱{{ number_format($item->pricePerDay, 2) }}</span>
                    <span class="rental-card__unit">per day</span>
                </p>
            </div>
        </section>

        <section class="rental-days rental-days--detail" aria-label="Availability for {{ $item->name }}">
            @foreach ($entry->availability as $day)
                <div class="rental-day {{ $day->isFull() ? 'rental-day--full' : '' }}" data-date="{{ $day->date }}">
                    <span class="rental-day__date">{{ $day->date }}</span>
                    <span class="rental-day__count">{{ $day->seats() }} available</span>
                </div>
            @endforeach
        </section>

        @if ($errors->any())
            <p class="notice notice--error" role="alert">{{ $errors->first() }}</p>
        @endif

        @if ($gate->canOrder)
            {{-- Date range + quantity stepper: two input groups (≤3 inputs). The
                 total computes server-side; the customer supplies nothing else. --}}
            <form class="rental-book-form" method="POST" action="{{ route('rentals.book', ['item' => $item->id]) }}">
                @csrf

                <label class="field">
                    <span class="field__label">From</span>
                    <input class="field__input" type="date" name="dateFrom" min="{{ $today }}" value="{{ old('dateFrom', $today) }}" required>
                </label>

                <label class="field">
                    <span class="field__label">To</span>
                    <input class="field__input" type="date" name="dateTo" min="{{ $today }}" value="{{ old('dateTo', $today) }}" required>
                </label>

                <label class="field">
                    <span class="field__label">Quantity</span>
                    <input class="field__input" type="number" name="qty" min="1" max="{{ max(1, $item->units) }}" value="{{ old('qty', 1) }}" step="1" required data-stepper="qty">
                </label>

                @error('rental')<span class="field__error">{{ $message }}</span>@enderror

                @auth
                    <button type="submit" class="primary-button">Book &amp; pay with GCash</button>
                @else
                    <a class="primary-button" href="{{ route('login') }}">Sign in with Google to book</a>
                @endauth
            </form>
        @else
            <div class="order-gate">
                <button class="primary-button" type="button" disabled aria-disabled="true">Book &amp; pay with GCash</button>
                <p class="order-gate__reason">{{ $gate->reason }}</p>
            </div>
        @endif
    </main>

    @include('menu.partials.footer')
</body>
</html>
