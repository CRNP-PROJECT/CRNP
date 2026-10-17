<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your cart · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    @include('menu.partials.header')

    <main class="site-main">
        <h1 class="cart-heading">Your cart</h1>

        @if ($cart->isEmpty())
            <section class="empty-state" aria-labelledby="cart-empty-heading">
                <h2 class="empty-state__title" id="cart-empty-heading">Your cart is empty</h2>
                <p>Browse the menu and tap an item to start your order.</p>
                <a class="primary-button" href="{{ route('home') }}">Back to menu</a>
            </section>
        @else
            {{-- Inline steppers and add-on picks: the customer supplies a
                 quantity and taps add-ons; the line total computes itself. --}}
            <ul class="cart-list">
                @foreach ($cart->lines() as $line)
                    <li class="cart-line">
                        <div class="cart-line__head">
                            <span class="cart-line__name">{{ $line->name }}</span>
                            <span class="cart-line__price">₱{{ number_format($line->lineTotal(), 2) }}</span>
                        </div>

                        @php($product = $products[$line->productId] ?? null)

                        <form class="cart-line__form" method="POST" action="{{ route('cart.update') }}">
                            @csrf
                            <input type="hidden" name="product" value="{{ $line->productId }}">

                            @if ($product !== null && $product->addOns !== [])
                                <fieldset class="addon-picker__set">
                                    <legend class="addon-picker__legend">Add-ons</legend>
                                    @foreach ($product->addOns as $addOn)
                                        <label class="addon-picker__option">
                                            <input type="checkbox" name="addOns[]" value="{{ $addOn->name }}" @checked(in_array($addOn->name, $savedAddOns[$line->productId] ?? [], true))>
                                            <span>{{ $addOn->name }}</span>
                                            <span class="addon-picker__price">+₱{{ number_format($addOn->price, 2) }}</span>
                                        </label>
                                    @endforeach
                                </fieldset>
                            @endif

                            <div class="stepper" role="group" aria-label="Quantity for {{ $line->name }}">
                                <button type="submit" class="stepper__button" name="quantity" value="{{ $line->quantity - 1 }}" aria-label="Fewer {{ $line->name }}">−</button>
                                <span class="stepper__value" aria-live="polite">{{ $line->quantity }}</span>
                                <button type="submit" class="stepper__button" name="quantity" value="{{ $line->quantity + 1 }}" aria-label="More {{ $line->name }}">+</button>
                            </div>

                            <button type="submit" class="link-button cart-line__save">Update</button>
                        </form>

                        <form method="POST" action="{{ route('cart.destroy', ['product' => $line->productId]) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="link-button">Remove</button>
                        </form>
                    </li>
                @endforeach
            </ul>

            <p class="cart-total">
                <span>Total</span>
                <span class="cart-total__amount">₱{{ number_format($cart->total(), 2) }}</span>
            </p>

            @if ($gate->canOrder)
                <a class="primary-button" href="{{ route('checkout.show') }}">Checkout</a>
            @else
                <div class="order-gate">
                    <button class="primary-button" type="button" disabled aria-disabled="true">Checkout</button>
                    <p class="order-gate__reason">{{ $gate->reason }}</p>
                </div>
            @endif
        @endif
    </main>

    @include('menu.partials.footer')
</body>
</html>
