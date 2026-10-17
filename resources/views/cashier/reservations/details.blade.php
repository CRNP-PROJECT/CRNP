@php
    /** @var \App\Reservations\ReservationPrefill $prefill */
    /** @var \App\Settings\ReservationArea[] $areas */
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reservation date &amp; party · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    @include('cashier.partials.register-header')

    <main class="site-main">
        <p class="site-main__copy">
            <a href="{{ route('cashier.reservations.create') }}">← Back</a>
        </p>

        <section class="guided-screen" aria-labelledby="step-two-heading">
            <p class="guided-screen__step">Step 2 of 2</p>
            <h1 id="step-two-heading">
                @if ($prefill->returning)
                    Welcome back, {{ $prefill->name }}
                @else
                    When is {{ $prefill->name !== '' ? $prefill->name : 'the guest' }} coming?
                @endif
            </h1>
            <p class="guided-screen__copy">
                {{ $prefill->type->label() }} · {{ $prefill->phone }}
                @if ($prefill->returning)
                    — name, party and preferred type prefilled from the last visit.
                @endif
            </p>

            @error('reservation')<p class="notice notice--error" role="alert">{{ $message }}</p>@enderror

            {{-- Step 2: when (date + time) and the party stepper — exactly 2 inputs. --}}
            <form method="POST" action="{{ route('cashier.reservations.store') }}" class="guided-form">
                @csrf
                <input type="hidden" name="name" value="{{ $prefill->name }}">
                <input type="hidden" name="phone" value="{{ $prefill->phone }}">
                <input type="hidden" name="type" value="{{ $prefill->type->value }}">
                <input type="hidden" name="preferredType" value="{{ $prefill->preferredType->value }}">

                <label class="field">
                    <span class="field__label">Date &amp; time</span>
                    <input
                        class="field__input"
                        type="datetime-local"
                        name="when"
                        value="{{ old('when', $today.'T18:00') }}"
                        required
                    >
                    @error('when')<span class="field__error">{{ $message }}</span>@enderror
                </label>

                <label class="field">
                    <span class="field__label">Party size</span>
                    <input
                        class="field__input field__input--narrow"
                        type="number"
                        name="party"
                        min="1"
                        max="10000"
                        value="{{ old('party', $prefill->party) }}"
                        required
                    >
                    @error('party')<span class="field__error">{{ $message }}</span>@enderror
                </label>

                <button type="submit" class="primary-button">Confirm reservation</button>
            </form>
        </section>
    </main>
</body>
</html>
