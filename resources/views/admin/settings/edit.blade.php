<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Settings · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    @include('admin.partials.nav')

    <main class="site-main">
        <h1>Business settings</h1>

        @if (session('status'))
            <p class="notice" role="status">{{ session('status') }}</p>
        @endif

        @unless ($settings->hasPaymentBlock())
            <p class="setup-banner" role="alert">
                <strong>Complete setup.</strong>
                Ordering stays closed until both the GCash number and the official
                QR image are configured.
            </p>
        @endunless

        {{-- 1. Hours per weekday + force-close --}}
        <section class="settings-panel" aria-labelledby="hours-heading">
            <h2 id="hours-heading">Business hours</h2>
            <p class="settings-panel__copy">
                Leave a day blank to close it. Hours evaluate in
                {{ config('settings.timezone') }}.
            </p>
            <form method="POST" action="{{ route('admin.settings.hours') }}" class="settings-form">
                @csrf
                @method('PUT')
                <table class="settings-table">
                    <thead>
                        <tr>
                            <th scope="col">Day</th>
                            <th scope="col">Opens</th>
                            <th scope="col">Closes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($weekdays as $day)
                            @php($window = $settings->hoursFor($day))
                            <tr>
                                <th scope="row">{{ $day->label() }}</th>
                                <td>
                                    <label class="visually-hidden" for="open-{{ $day->value }}">Opening time {{ $day->label() }}</label>
                                    <input
                                        class="field__input"
                                        type="time"
                                        id="open-{{ $day->value }}"
                                        name="hours[{{ $day->value }}][open]"
                                        value="{{ old("hours.{$day->value}.open", $window?->open) }}"
                                    >
                                    @error("hours.{$day->value}.open")<span class="field__error">{{ $message }}</span>@enderror
                                </td>
                                <td>
                                    <label class="visually-hidden" for="close-{{ $day->value }}">Closing time {{ $day->label() }}</label>
                                    <input
                                        class="field__input"
                                        type="time"
                                        id="close-{{ $day->value }}"
                                        name="hours[{{ $day->value }}][close]"
                                        value="{{ old("hours.{$day->value}.close", $window?->close) }}"
                                    >
                                    @error("hours.{$day->value}.close")<span class="field__error">{{ $message }}</span>@enderror
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <label class="toggle">
                    <input type="checkbox" name="forceClose" value="1" @checked(old('forceClose', $settings->forceClose))>
                    <span>Force-close the site now (emergency override)</span>
                </label>

                <button type="submit" class="primary-button">Save hours</button>
            </form>
        </section>

        {{-- 2. Scheduled date-range closures --}}
        <section class="settings-panel" aria-labelledby="closures-heading">
            <h2 id="closures-heading">Scheduled closures</h2>
            <p class="settings-panel__copy">
                A date range that closes the site by itself — holidays, configured once.
            </p>

            @if ($settings->closures === [])
                <p class="empty-state">No scheduled closures.</p>
            @else
                <ul class="closure-list">
                    @foreach ($settings->closures as $id => $closure)
                        <li class="closure-list__item">
                            <span>
                                <strong>{{ $closure->label }}</strong>
                                · {{ $closure->from }} → {{ $closure->to }}
                            </span>
                            <form method="POST" action="{{ route('admin.settings.closures.destroy', ['closure' => $id]) }}" class="inline-form">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="link-button">Remove</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif

            <form method="POST" action="{{ route('admin.settings.closures.store') }}" class="settings-form">
                @csrf
                <div class="settings-form__row">
                    <label class="field">
                        <span class="field__label">From</span>
                        <input class="field__input" type="date" name="from" value="{{ old('from') }}" required>
                        @error('from')<span class="field__error">{{ $message }}</span>@enderror
                    </label>
                    <label class="field">
                        <span class="field__label">To</span>
                        <input class="field__input" type="date" name="to" value="{{ old('to') }}" required>
                        @error('to')<span class="field__error">{{ $message }}</span>@enderror
                    </label>
                    <label class="field">
                        <span class="field__label">Label</span>
                        <input class="field__input" type="text" name="label" value="{{ old('label') }}" maxlength="60" placeholder="Christmas">
                        @error('label')<span class="field__error">{{ $message }}</span>@enderror
                    </label>
                </div>
                <button type="submit" class="primary-button">Add closure</button>
            </form>
        </section>

        {{-- 3. Announcement banner --}}
        <section class="settings-panel" aria-labelledby="banner-heading">
            <h2 id="banner-heading">Announcement banner</h2>
            <form method="POST" action="{{ route('admin.settings.banner') }}" class="settings-form">
                @csrf
                @method('PUT')
                <label class="field">
                    <span class="field__label">Message</span>
                    <input class="field__input" type="text" name="banner" value="{{ old('banner', $settings->bannerText) }}" maxlength="280" placeholder="Buy 1 take 1 milktea this weekend!">
                    @error('banner')<span class="field__error">{{ $message }}</span>@enderror
                </label>
                <label class="toggle">
                    <input type="checkbox" name="bannerVisible" value="1" @checked(old('bannerVisible', $settings->bannerVisible))>
                    <span>Show the banner on the site</span>
                </label>
                <button type="submit" class="primary-button">Save banner</button>
            </form>
        </section>

        {{-- 4. GCash payment block --}}
        <section class="settings-panel" aria-labelledby="payment-heading">
            <h2 id="payment-heading">GCash payment</h2>
            <p class="settings-panel__copy">
                Both the number and the official QR image are required before
                ordering opens. The QR is resized to
                {{ config('settings.qr_max_edge') }} px server-side and stored as
                base64 — the official image is scaled, never restyled.
            </p>

            @if ($settings->hasQr())
                <p class="settings-panel__status">
                    QR configured
                    <img class="qr-preview" src="{{ $settings->gcashQr }}" alt="Configured GCash QR">
                </p>
            @else
                <p class="settings-panel__status">No QR uploaded yet.</p>
            @endif

            <form method="POST" action="{{ route('admin.settings.payment') }}" class="settings-form" enctype="multipart/form-data">
                @csrf
                <label class="field">
                    <span class="field__label">GCash number</span>
                    <input class="field__input" type="text" name="gcashNumber" value="{{ old('gcashNumber', $settings->gcashNumber) }}" maxlength="40" placeholder="0917 123 4567" required>
                    @error('gcashNumber')<span class="field__error">{{ $message }}</span>@enderror
                </label>
                <label class="field">
                    <span class="field__label">Official QR image</span>
                    <input class="field__input" type="file" name="qr" accept="image/*">
                    @error('qr')<span class="field__error">{{ $message }}</span>@enderror
                </label>
                <button type="submit" class="primary-button">Save payment block</button>
            </form>
        </section>

        {{-- 5. Kitchen secret --}}
        <section class="settings-panel" aria-labelledby="kitchen-heading">
            <h2 id="kitchen-heading">Kitchen secret</h2>
            <p class="settings-panel__copy">
                The read-only kitchen board is opened by a shared-secret URL.
                Rotating replaces the secret, so the old link stops working
                immediately.
            </p>

            @if ($settings->kitchenSecret !== '')
                <p class="settings-panel__copy">
                    Board link:
                    <a href="{{ route('kitchen.board.secret', ['secret' => $settings->kitchenSecret]) }}">
                        {{ route('kitchen.board.secret', ['secret' => $settings->kitchenSecret]) }}
                    </a>
                </p>
            @else
                <p class="settings-panel__copy">No secret set yet — rotate one to open the board.</p>
            @endif

            <form method="POST" action="{{ route('admin.settings.kitchen-secret') }}">
                @csrf
                <button type="submit" class="primary-button">Rotate secret</button>
            </form>
        </section>

        {{-- 6. Reservation capacities --}}
        <section class="settings-panel" aria-labelledby="capacities-heading">
            <h2 id="capacities-heading">Reservation capacities</h2>
            <p class="settings-panel__copy">
                Seats per area for the public occupancy view. Blank areas keep
                their smart default.
            </p>
            <form method="POST" action="{{ route('admin.settings.capacities') }}" class="settings-form">
                @csrf
                @method('PUT')
                @foreach ($areas as $area)
                    <label class="field">
                        <span class="field__label">{{ $area->label() }} (default {{ $area->defaultCapacity() }})</span>
                        <input
                            class="field__input"
                            type="number"
                            name="capacities[{{ $area->value }}]"
                            min="0"
                            max="10000"
                            value="{{ old("capacities.{$area->value}", $settings->capacityFor($area)) }}"
                        >
                        @error("capacities.{$area->value}")<span class="field__error">{{ $message }}</span>@enderror
                    </label>
                @endforeach
                <button type="submit" class="primary-button">Save capacities</button>
            </form>
        </section>
    </main>
</body>
</html>
