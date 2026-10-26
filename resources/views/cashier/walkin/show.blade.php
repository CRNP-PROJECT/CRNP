<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Walk-in {{ $order->code }} · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    @include('cashier.partials.register-header')

    <main class="site-main">
        <header class="walkin-head">
            <div>
                <p class="walkin-head__label">Walk-in order code</p>
                <p class="walkin-head__code">{{ $order->code }}</p>
            </div>
            <a class="link-button" href="{{ route('cashier.register') }}">Back to register</a>
        </header>

        @if ($errors->any())
            <p class="notice notice--error" role="alert">{{ $errors->first() }}</p>
        @endif

        <div class="walkin-grid">
            {{-- Tap tiles: each adds one of the product at its computed selling
                 price. Promo prices come from the product, never re-typed. --}}
            <section class="walkin-menu" aria-labelledby="walkin-menu-heading">
                <h2 class="walkin-panel__title" id="walkin-menu-heading">Menu</h2>

                @if ($categories !== [])
                    {{-- One compact dropdown instead of a chip per category: a
                         long menu stays tidy, and picking a category reloads
                         the tiles filtered to it. --}}
                    <details class="category-menu">
                        <summary class="category-menu__button">
                            <span class="category-menu__text">
                                <span class="category-menu__label">Category</span>
                                <span class="category-menu__value">{{ $selectedCategoryName ?? 'All Products' }}</span>
                            </span>
                            <svg class="category-menu__chevron" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                <path d="m6 9 6 6 6-6"/>
                            </svg>
                        </summary>
                        <ul class="category-menu__list">
                            <li>
                                <a
                                    class="category-menu__item"
                                    href="{{ route('cashier.walkins.show', ['walkin' => $order->id]) }}"
                                    @if ($selectedCategory === null) aria-current="true" @endif
                                >All Products</a>
                            </li>
                            @foreach ($categories as $id => $category)
                                <li>
                                    <a
                                        class="category-menu__item"
                                        href="{{ route('cashier.walkins.show', ['walkin' => $order->id, 'category' => $id]) }}"
                                        @if ($selectedCategory === $id) aria-current="true" @endif
                                    >{{ $category->name }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endif

                @if ($menu === [])
                    <p class="empty-state">
                        @if ($selectedCategory === null)
                            No menu items yet — the owner adds them from the back office.
                        @else
                            No items in this category — pick another chip or All Products.
                        @endif
                    </p>
                @else
                    <div class="tile-grid">
                        @foreach ($menu as $product)
                            <form method="POST" action="{{ route('cashier.walkins.items.store', ['walkin' => $order->id]) }}" class="tile">
                                @csrf
                                <input type="hidden" name="product" value="{{ $product->id }}">
                                <input type="hidden" name="quantity" value="1">

                                @if ($product->addOns === [])
                                    <button type="submit" class="tile__tap" @disabled(! $order->isPending())>
                                        <span class="tile__name">{{ $product->name }}</span>
                                        <span class="tile__price">
                                            @if ($product->promo !== null)
                                                <s>₱{{ number_format($product->price, 2) }}</s>
                                                ₱{{ number_format($product->sellingPrice(), 2) }}
                                            @else
                                                ₱{{ number_format($product->price, 2) }}
                                            @endif
                                        </span>
                                    </button>
                                @else
                                    <span class="tile__name">{{ $product->name }}</span>
                                    <span class="tile__price">
                                        @if ($product->promo !== null)
                                            <s>₱{{ number_format($product->price, 2) }}</s>
                                            ₱{{ number_format($product->sellingPrice(), 2) }}
                                        @else
                                            ₱{{ number_format($product->price, 2) }}
                                        @endif
                                    </span>

                                    {{-- Per-product add-ons: tick one or more, and the
                                         server adds each price to this line. --}}
                                    <fieldset class="tile__addons">
                                        <legend class="tile__addons-title">Add-ons</legend>
                                        @foreach ($product->addOns as $addOn)
                                            <label class="tile__addon">
                                                <input type="checkbox" name="addOns[]" value="{{ $addOn->name }}" @disabled(! $order->isPending())>
                                                <span class="tile__addon-name">{{ $addOn->name }}</span>
                                                <span class="tile__addon-price">+₱{{ number_format($addOn->price, 2) }}</span>
                                            </label>
                                        @endforeach
                                    </fieldset>

                                    <button type="submit" class="tile__add" aria-label="Add {{ $product->name }} to order" @disabled(! $order->isPending())>Add to order</button>
                                @endif
                            </form>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="walkin-cart" aria-labelledby="walkin-cart-heading">
                <h2 class="walkin-panel__title" id="walkin-cart-heading">Order</h2>

                {{-- Service type lives here, on the open order: a change of
                     mind switches the type in place and keeps every line. --}}
                <div class="walkin-service">
                    <span class="walkin-service__label" id="service-type-label">Service type</span>
                    <form method="POST" action="{{ route('cashier.walkins.service-type', ['walkin' => $order->id]) }}">
                        @csrf
                        @method('PATCH')
                        <div class="segmented" role="group" aria-labelledby="service-type-label">
                            @foreach (\App\Orders\ServiceType::cases() as $service)
                                <label class="segmented__option">
                                    <input
                                        type="radio"
                                        name="serviceType"
                                        value="{{ $service->value }}"
                                        @checked($order->serviceType === $service)
                                        @disabled(! $order->isPending())
                                        onchange="this.form.submit()"
                                    >
                                    <span>{{ $service->label() }}</span>
                                </label>
                            @endforeach
                        </div>
                        @if ($order->isPending())
                            <noscript><button type="submit" class="link-button">Update service type</button></noscript>
                        @endif
                    </form>
                </div>

                @if ($order->lines === [])
                    <p class="empty-state">No items yet — tap a tile to start the order.</p>
                @else
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th scope="col">Item</th>
                                <th scope="col">Qty</th>
                                <th scope="col">Line</th>
                                <th scope="col"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($order->lines as $line)
                                <tr>
                                    <td>
                                        {{ $line->name }}
                                        @if ($line->addOns !== [])
                                            <ul class="walkin-line__addons">
                                                @foreach ($line->addOns as $addOn)
                                                    <li>+ {{ $addOn->name }} <span>₱{{ number_format($addOn->price, 2) }}</span></li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </td>
                                    <td>{{ $line->quantity }}</td>
                                    <td>₱{{ number_format($line->lineTotal(), 2) }}</td>
                                    <td>
                                        @if ($order->isPending())
                                            <form method="POST" action="{{ route('cashier.walkins.items.destroy', ['walkin' => $order->id, 'line' => $loop->index]) }}" class="inline-form">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="link-button">Remove</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                <p class="walkin-total">
                    <span>Total</span>
                    <span class="walkin-total__amount">₱{{ number_format($order->total, 2) }}</span>
                </p>

                @if ($order->isPending())
                    {{-- The cashier types exactly one number: the cash handed over.
                         The split, change, and any required ref# derive from it. --}}
                    <form method="POST" action="{{ route('cashier.walkins.settle', ['walkin' => $order->id]) }}" class="settle-form">
                        @csrf
                        <label class="field">
                            <span class="field__label">Cash handed over (₱)</span>
                            <input class="field__input" type="number" name="cash" step="0.01" min="0" value="{{ old('cash') }}" required>
                            @error('cash')<span class="field__error">{{ $message }}</span>@enderror
                        </label>

                        <label class="field">
                            <span class="field__label">GCash ref# (only when cash is under the total)</span>
                            <input class="field__input" type="text" name="gcashRef" value="{{ old('gcashRef') }}" maxlength="64">
                            @error('gcashRef')<span class="field__error">{{ $message }}</span>@enderror
                        </label>

                        <button type="submit" class="primary-button">Settle &amp; print receipt</button>
                    </form>
                @else
                    <a class="primary-button" href="{{ route('cashier.walkins.receipt', ['walkin' => $order->id]) }}">Print receipt</a>
                @endif
            </section>
        </div>
    </main>
</body>
</html>
