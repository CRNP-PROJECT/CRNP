<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Order {{ $state->code }} · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    @include('menu.partials.header')

    @php
        // The proof surface is only live while the order is still in a payment
        // state; once it is cooking/served/dismissed/rejected the customer
        // attaches nothing (the controller enforces the same guard).
        $canAttachProof = in_array($state->status, ['awaiting_payment', 'verifying'], true);
    @endphp

    {{--
        The order tracker: a live status screen polling the state endpoint every
        5–10 s (no websockets). The order code is shown, never typed, and the
        account-bound active order reopens with zero typing. The GCash number and
        the official QR image sit untouched inside the branded payment frame.
    --}}
    <main
        class="site-main tracker"
        data-poll-url="{{ route('orders.state', ['order' => $state->orderId]) }}"
        data-poll-seconds="{{ $pollSeconds }}"
    >
        <header class="tracker__head">
            <p class="tracker__code-label">Order code</p>
            <p class="tracker__code">{{ $state->code }}</p>
        </header>

        <p
            class="tracker__status"
            id="tracker-status"
            role="status"
            aria-live="polite"
            data-status="{{ $state->status }}"
        >{{ $state->label }}</p>

        @if ($state->pickupAt !== null)
            <p class="tracker__pickup">
                Pickup at
                {{ \Illuminate\Support\Carbon::parse($state->pickupAt)->timezone(config('settings.timezone'))->format('g:i A') }}
            </p>
        @else
            <p class="tracker__pickup">Pickup ASAP</p>
        @endif

        {{-- The payment block: the GCash number to copy/pay-by-number and the
             official QR image, never re-rendered. --}}
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
            <span>Total</span>
            <span class="tracker__total-amount">₱{{ number_format($state->total, 2) }}</span>
        </p>

        {{-- A flagged hold shows the entered amount beside the order total, so
             the customer can self-correct at the counter — no blame, no reason
             codes. #27's Reject writes the same sentence; the numbers ride on
             the order node either way. Rendered always and revealed by the
             poll, so a hold caused by the customer's own upload appears without
             a page reload. --}}
        <section class="tracker__hold" id="tracker-hold" aria-labelledby="hold-heading" @if ($state->enteredAmount === null) hidden @endif>
            <h2 class="tracker__hold-title" id="hold-heading">Payment needs checking — see the counter</h2>
            <p class="tracker__hold-numbers">
                <span class="tracker__hold-figure">
                    <span class="tracker__hold-label">Amount you entered</span>
                    <span class="tracker__hold-value" id="hold-entered">@if ($state->enteredAmount !== null)₱{{ number_format($state->enteredAmount, 2) }}@endif</span>
                </span>
                <span class="tracker__hold-figure">
                    <span class="tracker__hold-label">Order total</span>
                    <span class="tracker__hold-value" id="hold-total">₱{{ number_format($state->total, 2) }}</span>
                </span>
            </p>
        </section>

        @if ($canAttachProof)
            @include('menu.partials.proof-upload', [
                'proofStartUrl' => route('orders.proof.start', ['order' => $state->orderId]),
                'proofStoreUrl' => route('orders.proof.store', ['order' => $state->orderId]),
                'proofTotal' => $state->total,
            ])
        @endif

        <p class="tracker__hint">
            This screen refreshes itself. Keep it open — it flips to READY the
            instant the counter marks your order served.
        </p>

        {{-- WCAG 2.2 SC 2.2.2: auto-updating content carries a pause control. --}}
        <p class="tracker__controls">
            <button type="button" class="link-button" id="tracker-pause" aria-pressed="false">Pause updates</button>
        </p>
    </main>

    <script>
        (function () {
            var root = document.querySelector('.tracker');
            if (!root) { return; }

            var url = root.getAttribute('data-poll-url');
            var seconds = parseInt(root.getAttribute('data-poll-seconds'), 10) || 5;
            var status = document.getElementById('tracker-status');
            var hold = document.getElementById('tracker-hold');
            var holdEntered = document.getElementById('hold-entered');
            var holdTotal = document.getElementById('hold-total');
            var proofPanel = document.querySelector('[data-proof-upload]');
            var proofStates = ['awaiting_payment', 'verifying'];
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

            function pesos(value) {
                return '₱' + Number(value).toFixed(2);
            }

            // The flagged-hold numbers ride on the order node, so the poll can
            // reveal them even when the customer's own upload caused the hold
            // without a page reload.
            function renderHold(state) {
                if (!hold) { return; }

                if (state.enteredAmount === null || state.enteredAmount === undefined) {
                    hold.hidden = true;
                    return;
                }

                if (holdEntered) { holdEntered.textContent = pesos(state.enteredAmount); }
                if (holdTotal) { holdTotal.textContent = pesos(state.total); }
                hold.hidden = false;
            }

            // The proof surface is live only while the order is still in a
            // payment state; once it verifies/flags the server drops it, and
            // the client hides it the same instant on the poll.
            function poll() {
                if (paused) { return; }

                fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                    .then(function (response) { return response.ok ? response.json() : null; })
                    .then(function (state) {
                        if (!state || !status) { return; }
                        status.textContent = state.label;
                        status.setAttribute('data-status', state.status);
                        renderHold(state);

                        if (proofPanel && proofStates.indexOf(state.status) === -1) {
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

    @include('menu.partials.footer')
</body>
</html>
