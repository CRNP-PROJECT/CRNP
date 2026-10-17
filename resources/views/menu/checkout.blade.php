<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Checkout · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    @include('menu.partials.header')

    <main class="site-main">
        <h1 class="cart-heading">Checkout</h1>

        @if ($errors->any())
            <p class="notice notice--error" role="alert">{{ $errors->first() }}</p>
        @endif

        @if ($cart->isEmpty())
            <section class="empty-state" aria-labelledby="checkout-empty-heading">
                <h2 class="empty-state__title" id="checkout-empty-heading">Your cart is empty</h2>
                <p>Add an item before checking out.</p>
                <a class="primary-button" href="{{ route('home') }}">Back to menu</a>
            </section>
        @else
            <section class="checkout-summary" aria-labelledby="checkout-summary-heading">
                <h2 id="checkout-summary-heading">Your order</h2>
                <ul class="cart-list cart-list--summary">
                    @foreach ($cart->lines() as $line)
                        <li class="cart-line__head">
                            <span class="cart-line__name">{{ $line->quantity }} × {{ $line->name }}</span>
                            <span class="cart-line__price">₱{{ number_format($line->lineTotal(), 2) }}</span>
                        </li>
                    @endforeach
                </ul>
                <p class="cart-total">
                    <span>Total</span>
                    <span class="cart-total__amount">₱{{ number_format($cart->total(), 2) }}</span>
                </p>
            </section>

            {{-- The 3-tap flow: cart → pickup time (default ASAP) → Place order.
                 An empty pickup means ASAP; the picker's floor is now + 15
                 minutes, validated again server-side, so no error can follow
                 payment. --}}
            <form class="checkout-form" method="POST" action="{{ route('checkout.store') }}">
                @csrf
                <input type="hidden" name="idempotencyKey" value="{{ $idempotencyKey }}">

                <label class="field">
                    <span class="field__label">Pickup time (leave blank for ASAP)</span>
                    <input
                        class="field__input"
                        type="datetime-local"
                        name="pickup"
                        min="{{ $minPickup }}"
                        value="{{ old('pickup') }}"
                    >
                    <span class="field__hint">
                        Defaults to ASAP. Earliest pickup is
                        {{ \Illuminate\Support\Carbon::parse($earliestPickup)->timezone(config('settings.timezone'))->format('g:i A') }}.
                    </span>
                    @error('pickup')<span class="field__error">{{ $message }}</span>@enderror
                </label>

                @error('cart')<span class="field__error">{{ $message }}</span>@enderror

                @if ($gate->canOrder)
                    <button type="submit" class="primary-button">Place order</button>
                @else
                    <div class="order-gate">
                        <button class="primary-button" type="button" disabled aria-disabled="true">Place order</button>
                        <p class="order-gate__reason">{{ $gate->reason }}</p>
                    </div>
                @endif
            </form>
        @endif
    </main>

    @include('menu.partials.footer')
</body>
</html>
