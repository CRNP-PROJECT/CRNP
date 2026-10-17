@php
    /** @var \App\Rentals\RentalCounter $counter */
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
    @include('cashier.partials.register-header')

    <main class="site-main">
        <h1 class="queue__title">Rentals</h1>

        @if (session('status'))
            <p class="notice" role="status">{{ session('status') }}</p>
        @endif

        <section class="queue-section" aria-labelledby="rentals-flagged-heading">
            <h2 class="queue-section__title" id="rentals-flagged-heading">Flagged proofs</h2>
            <p class="queue-section__hint">Entered amount or ref# mismatch — Confirm accepts the booking (units reserved), Cancel ends it. Stock stays put either way.</p>

            @if ($counter->flagged === [])
                <p class="empty-state">No flagged rental proofs — the line is clear.</p>
            @else
                <ul class="queue-list">
                    @foreach ($counter->flagged as $booking)
                        <li class="queue-row">
                            <span class="queue-row__code">{{ $booking->code }}</span>
                            <span class="queue-row__figures">
                                <span class="queue-row__figure">
                                    <span class="queue-row__figure-label">Entered</span>
                                    <span class="queue-row__figure-value">₱{{ number_format($booking->enteredAmount ?? $booking->total, 2) }}</span>
                                </span>
                                <span class="queue-row__figure">
                                    <span class="queue-row__figure-label">Total</span>
                                    <span class="queue-row__figure-value">₱{{ number_format($booking->total, 2) }}</span>
                                </span>
                            </span>
                            <span class="queue-row__actions">
                                <form method="POST" action="{{ route('cashier.rentals.confirm-proof', ['booking' => $booking->id]) }}" class="queue-row__action">
                                    @csrf
                                    <button type="submit" class="primary-button">Confirm</button>
                                </form>
                                <form method="POST" action="{{ route('cashier.rentals.cancel', ['booking' => $booking->id]) }}" class="queue-row__action" onsubmit="return confirm('Cancel this rental booking? This cannot be undone.');">
                                    @csrf
                                    <button type="submit" class="link-button">Cancel</button>
                                </form>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="queue-section" aria-labelledby="rentals-handover-heading">
            <h2 class="queue-section__title" id="rentals-handover-heading">To hand over</h2>
            <p class="queue-section__hint">Paid and confirmed — the tap releases the units (stock falls).</p>

            @if ($counter->toHandOver === [])
                <p class="empty-state">Nothing waiting for pickup.</p>
            @else
                <ul class="queue-list">
                    @foreach ($counter->toHandOver as $booking)
                        <li class="queue-row">
                            <span class="queue-row__code">{{ $booking->code }}</span>
                            <span class="queue-row__total">{{ $booking->qty }} × {{ $booking->period->fromString() }} → {{ $booking->period->toString() }}</span>
                            <form method="POST" action="{{ route('cashier.rentals.hand-over', ['booking' => $booking->id]) }}" class="queue-row__action">
                                @csrf
                                <button type="submit" class="primary-button">Hand over</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="queue-section" aria-labelledby="rentals-return-heading">
            <h2 class="queue-section__title" id="rentals-return-heading">To return</h2>
            <p class="queue-section__hint">On hire — the tap brings the units back (stock rises).</p>

            @if ($counter->toReturn === [])
                <p class="empty-state">No units out on hire.</p>
            @else
                <ul class="queue-list">
                    @foreach ($counter->toReturn as $booking)
                        <li class="queue-row">
                            <span class="queue-row__code">{{ $booking->code }}</span>
                            <span class="queue-row__total">Due {{ $booking->returnDueAt !== null ? \Illuminate\Support\Carbon::parse($booking->returnDueAt)->timezone(config('settings.timezone'))->format('M j, g:i A') : '—' }}</span>
                            <form method="POST" action="{{ route('cashier.rentals.confirm-return', ['booking' => $booking->id]) }}" class="queue-row__action">
                                @csrf
                                <button type="submit" class="primary-button">Confirm return</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="queue-section" aria-labelledby="rentals-overdue-heading">
            <h2 class="queue-section__title" id="rentals-overdue-heading">Overdue</h2>
            <p class="queue-section__hint">Past the return-due date — visible, no penalty.</p>

            @if ($counter->overdue === [])
                <p class="empty-state">No overdue rentals — all on schedule.</p>
            @else
                <ul class="queue-list">
                    @foreach ($counter->overdue as $booking)
                        <li class="queue-row queue-row--overdue">
                            <span class="queue-row__code">{{ $booking->code }}</span>
                            <span class="queue-row__total">
                                Overdue — due
                                {{ $booking->returnDueAt !== null ? \Illuminate\Support\Carbon::parse($booking->returnDueAt)->timezone(config('settings.timezone'))->format('M j, g:i A') : '—' }}
                            </span>
                            <form method="POST" action="{{ route('cashier.rentals.confirm-return', ['booking' => $booking->id]) }}" class="queue-row__action">
                                @csrf
                                <button type="submit" class="primary-button">Confirm return</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </main>
</body>
</html>
