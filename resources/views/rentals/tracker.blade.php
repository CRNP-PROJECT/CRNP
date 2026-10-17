@php
    /** @var \App\Rentals\RentalTrackerState $state */
    $canAttachProof = in_array($state->status, ['awaiting_payment', 'verifying'], true);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Rental {{ $state->code }} · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    @include('menu.partials.header')

    {{--
        The rental booking tracker: a live status screen polling the state
        endpoint every 5–10 s (no websockets). The code is shown, never typed,
        and the account-bound booking reopens with zero typing. The GCash number
        and the official QR image sit untouched inside the branded payment frame,
        and the proof surface is the SAME gate as an order's (#24).
    --}}
    <main
        class="site-main tracker"
        data-poll-url="{{ route('rentals.state', ['booking' => $state->bookingId]) }}"
        data-poll-seconds="{{ $pollSeconds }}"
    >
        <header class="tracker__head">
            <p class="tracker__code-label">Rental code</p>
            <p class="tracker__code">{{ $state->code }}</p>
        </header>

        <p
            class="tracker__status"
            id="tracker-status"
            role="status"
            aria-live="polite"
            data-status="{{ $state->status }}"
        >{{ $state->label }}</p>

        <p class="tracker__pickup">
            {{ $state->itemName }} · {{ $state->qty }} unit(s) ·
            {{ $state->dateFrom }} → {{ $state->dateTo }}
        </p>

        @if ($gcashNumber !== '' || $gcashQr !== '')
            <section class="payment-frame" aria-labelledby="payment-heading">
                <h2 class="payment-frame__title" id="payment-heading">Pay with GCash</h2>

                @if ($gcashQr !== '')
                    <img class="payment-frame__qr" src="{{ $gcashQr }}" alt="Official GCash QR code">
                @endif

                @if ($gcashNumber !== '')
                    <p class="payment-frame__number">
                        <span class="payment-frame__number-label">GCash number</span>
                        <span class="payment-frame__number-value">{{ $gcashNumber }}</span>
                    </p>
                @endif

                @if ($state->deadlineAt !== null)
                    <p class="payment-frame__deadline">
                        Pay by
                        <span class="payment-frame__deadline-value">
                            {{ \Illuminate\Support\Carbon::parse($state->deadlineAt)->timezone(config('settings.timezone'))->format('g:i A') }}
                        </span>
                    </p>
                @endif
            </section>
        @endif

        <p class="tracker__total">
            <span>Total (prepaid in full)</span>
            <span class="tracker__total-amount">₱{{ number_format($state->total, 2) }}</span>
        </p>

        @if ($canAttachProof)
            @include('menu.partials.proof-upload', [
                'proofStartUrl' => route('rentals.proof.start', ['booking' => $state->bookingId]),
                'proofStoreUrl' => route('rentals.proof.store', ['booking' => $state->bookingId]),
                'proofTotal' => $state->total,
            ])
        @endif

        <p class="tracker__hint">
            This screen refreshes itself. Keep it open — the counter hands the
            units over once your payment verifies.
        </p>

        {{-- WCAG 2.2 SC 2.2.2: auto-updating content carries a pause control. --}}
        <p class="tracker__controls">
            <button type="button" class="link-button" id="tracker-pause" aria-pressed="false">Pause updates</button>
        </p>
    </main>

    @include('menu.partials.footer')

    <script>
        (function () {
            var root = document.querySelector('.tracker');
            if (!root) { return; }

            var url = root.getAttribute('data-poll-url');
            var seconds = parseInt(root.getAttribute('data-poll-seconds'), 10) || 5;
            var status = document.getElementById('tracker-status');
            var proofPanel = document.querySelector('[data-proof-upload]');
            var pause = document.getElementById('tracker-pause');
            var paused = false;

            // WCAG 2.2 SC 2.2.2: auto-updating content carries a pause control.
            // Pausing halts the poll loop; resuming restarts it immediately.
            function setPaused(next) {
                paused = next;
                if (pause) {
                    pause.textContent = paused ? 'Resume updates' : 'Pause updates';
                    pause.setAttribute('aria-pressed', paused ? 'true' : 'false');
                }
                if (!paused) { window.setTimeout(poll, 0); }
            }

            if (pause) {
                pause.addEventListener('click', function () { setPaused(!paused); });
            }

            // The proof surface is live only while the booking is still in a
            // payment state; once it verifies/flags the server keeps it live for
            // a flag, and the client hides it the same instant it leaves.
            function poll() {
                if (paused) { return; }

                fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                    .then(function (response) { return response.ok ? response.json() : null; })
                    .then(function (state) {
                        if (!state || !status) { return; }
                        status.textContent = state.label;
                        status.setAttribute('data-status', state.status);

                        if (proofPanel && ['awaiting_payment', 'verifying'].indexOf(state.status) === -1) {
                            proofPanel.hidden = true;
                        }
                    })
                    .catch(function () { /* keep the last known state on a blip */ })
                    .then(function () { window.setTimeout(poll, seconds * 1000); });
            }

            window.setTimeout(poll, seconds * 1000);
        })();

        @include('menu.partials.proof-upload-script')
    </script>
</body>
</html>
