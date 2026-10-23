<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt {{ $order->code }} · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <main class="site-main">
        <article class="receipt" aria-labelledby="receipt-heading">
            <header class="receipt__head">
                <span class="logo-chip"><img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name') }}"></span>
                <h1 class="receipt__title" id="receipt-heading">{{ config('app.name') }}</h1>
                <p class="receipt__code">
                    <span class="receipt__code-label">Order code</span>
                    <span class="receipt__code-value">{{ $order->code }}</span>
                </p>
                @if ($order->table !== null)
                    <p class="receipt__table">
                        <span class="receipt__code-label">Table</span>
                        <span class="receipt__code-value">{{ $order->table }}</span>
                    </p>
                @endif
                @if ($order->serviceType !== null)
                    <p class="receipt__table">
                        <span class="receipt__code-label">Service</span>
                        <span class="receipt__code-value">{{ $order->serviceType->label() }}</span>
                    </p>
                @endif
            </header>

            <table class="receipt__lines">
                <tbody>
                    @foreach ($order->lines as $line)
                        <tr>
                            <td>{{ $line->quantity }} × {{ $line->name }}</td>
                            <td class="receipt__amount">₱{{ number_format($line->lineTotal(), 2) }}</td>
                        </tr>
                        @foreach ($line->addOns as $addOn)
                            <tr class="receipt__addon">
                                <td>+ {{ $addOn->name }}</td>
                                <td class="receipt__amount">₱{{ number_format($addOn->price, 2) }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>

            <p class="receipt__total">
                <span>Total</span>
                <span class="receipt__amount">₱{{ number_format($order->total, 2) }}</span>
            </p>

            {{-- The tender breakdown: one leg per payment, the change due, and
                 the GCash ref# when a GCash leg is present. --}}
            <table class="receipt__payments">
                <tbody>
                    @foreach ($order->payments as $payment)
                        <tr>
                            <td>{{ $payment->method->label() }}</td>
                            <td class="receipt__amount">₱{{ number_format($payment->amount, 2) }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td>Change</td>
                        <td class="receipt__amount">₱{{ number_format($order->change, 2) }}</td>
                    </tr>
                    @if ($order->gcashRef !== null)
                        <tr>
                            <td>GCash ref#</td>
                            <td class="receipt__amount">{{ $order->gcashRef }}</td>
                        </tr>
                    @endif
                </tbody>
            </table>

            <footer class="receipt__foot">
                <p>Handled by {{ $order->handledBy?->name ?? $cashier?->name ?? 'Staff' }}</p>
                @if ($order->settledAt !== null)
                    <p>{{ \Illuminate\Support\Carbon::parse($order->settledAt)->timezone(config('settings.timezone'))->format('M j, Y g:i A') }}</p>
                @endif
            </footer>
        </article>

        <div class="receipt-actions">
            <button type="button" class="primary-button" onclick="window.print()">Print receipt</button>
            <a class="link-button" href="{{ route('cashier.register') }}">New walk-in</a>
        </div>
    </main>
</body>
</html>
