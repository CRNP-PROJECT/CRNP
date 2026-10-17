@php
    /** @var \App\Settings\ReservationArea[] $areas */
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>New reservation · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    @include('cashier.partials.register-header')

    <main class="site-main">
        <p class="site-main__copy">
            <a href="{{ route('cashier.reservations.index') }}">← Reservations</a>
        </p>

        <section class="guided-screen" aria-labelledby="step-one-heading">
            <p class="guided-screen__step">Step 1 of 2</p>
            <h1 id="step-one-heading">Who is the reservation for?</h1>
            <p class="guided-screen__copy">
                Type the phone first — a returning customer's name, party and
                preferred type fill themselves in on the next step.
            </p>

            @error('reservation')<p class="notice notice--error" role="alert">{{ $message }}</p>@enderror

            {{-- Step 1: name · phone · type chips — exactly 3 inputs. --}}
            <form method="POST" action="{{ route('cashier.reservations.details') }}" class="guided-form">
                @csrf
                <label class="field">
                    <span class="field__label">Phone</span>
                    <input
                        class="field__input"
                        type="tel"
                        name="phone"
                        value="{{ old('phone') }}"
                        inputmode="tel"
                        autocomplete="off"
                        required
                        data-typeahead-url="{{ route('cashier.reservations.lookup') }}"
                    >
                    @error('phone')<span class="field__error">{{ $message }}</span>@enderror
                </label>

                <label class="field">
                    <span class="field__label">Name <span class="field__hint">(blank for a returning customer)</span></span>
                    <input class="field__input" type="text" name="name" value="{{ old('name') }}" maxlength="120">
                    @error('name')<span class="field__error">{{ $message }}</span>@enderror
                </label>

                <fieldset class="chip-set">
                    <legend class="field__label">Type</legend>
                    @foreach ($areas as $area)
                        <label class="chip">
                            <input
                                type="radio"
                                name="type"
                                value="{{ $area->value }}"
                                @checked(old('type') === $area->value)
                            >
                            <span>{{ $area->label() }}</span>
                        </label>
                    @endforeach
                    @error('type')<span class="field__error">{{ $message }}</span>@enderror
                </fieldset>

                <button type="submit" class="primary-button">Next</button>
            </form>
        </section>
    </main>
</body>
</html>
