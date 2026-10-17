<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $product->name }} · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script src="{{ asset('js/product-theme.js') }}" defer></script>
</head>
<body>
    @include('menu.partials.header')

    <main class="site-main site-main--product">
        @if ($banner !== null)
            <p class="announcement-banner" role="status">{{ $banner }}</p>
        @endif

        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a class="breadcrumb__link @if ($category === null) breadcrumb__link--current @endif" href="{{ route('home') }}">
                <span class="breadcrumb__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false" aria-hidden="true">
                        <path d="M4 10.5 12 4l8 6.5"/>
                        <path d="M6 9.5V20h12V9.5"/>
                    </svg>
                </span>
                Menu
            </a>
            @if ($category !== null)
                <span class="breadcrumb__sep" aria-hidden="true">/</span>
                <a class="breadcrumb__link breadcrumb__link--current" href="{{ route('home', ['category' => $category->id]) }}">{{ $category->name }}</a>
            @endif
        </nav>

        <article class="product-detail"
            data-product-id="{{ $product->id }}"
            data-product-name="{{ $product->name }}"
            data-product-category="{{ $category?->name ?? '' }}"
            data-product-theme="{{ $product->theme ?? '' }}">
            <div class="product-detail__media">
                @if ($product->imageBase64 !== '')
                    <img class="product-detail__image" src="data:image/jpeg;base64,{{ $product->imageBase64 }}" alt="{{ $product->name }}">
                @else
                    <span class="product-detail__placeholder" aria-hidden="true">{{ mb_substr($product->name, 0, 1) }}</span>
                @endif
            </div>

            <div class="product-detail__body">
                <div class="product-detail__head">
                    @if ($category !== null)
                        <span class="product-detail__category">{{ $category->name }}</span>
                    @endif

                    <form class="product-detail__favorite" method="POST" action="{{ route('favorites.toggle', ['product' => $product->id]) }}">
                        @csrf
                        <button
                            type="submit"
                            class="favorite-toggle"
                            aria-pressed="{{ $isFavorite ? 'true' : 'false' }}"
                            aria-label="{{ $isFavorite ? 'Remove from favorites' : 'Save to favorites' }}"
                        >
                            <span class="favorite-toggle__glyph" aria-hidden="true">{{ $isFavorite ? '♥' : '♡' }}</span>
                            <span class="favorite-toggle__label">{{ $isFavorite ? 'Saved' : 'Save' }}</span>
                        </button>
                    </form>
                </div>

                <h1 class="product-detail__name">{{ $product->name }}</h1>

                <p class="product-detail__price">
                    @if ($product->promo !== null)
                        <s>₱{{ number_format($product->price, 2) }}</s>
                        <span class="product-detail__price-now">₱{{ number_format($product->promo->promoPrice, 2) }}</span>
                        <span class="tag tag--promo">{{ $product->promo->label() }}</span>
                    @else
                        <span class="product-detail__price-now">₱{{ number_format($product->price, 2) }}</span>
                    @endif
                    @if ($isTopThree)
                        <span class="tag tag--top">Top 3</span>
                    @endif
                </p>

                @include('menu.partials.store-badge', ['gate' => $gate])

                @if ($product->description !== '')
                    <p class="product-detail__description">{{ $product->description }}</p>
                @endif

                @include('menu.partials.order-gate', ['gate' => $gate, 'ctaIcons' => true])

                @if ($product->addOns !== [])
                    <form class="addon-picker" method="POST" action="{{ route('prefs.add-ons', ['product' => $product->id]) }}">
                        @csrf
                        <fieldset class="addon-picker__set">
                            <legend class="addon-picker__legend">Add-ons</legend>
                            @foreach ($product->addOns as $addOn)
                                <label class="addon-picker__option">
                                    <input type="checkbox" name="addOns[]" value="{{ $addOn->name }}" @checked(in_array($addOn->name, $selectedAddOns, true))>
                                    <span>{{ $addOn->name }}</span>
                                    <span class="addon-picker__price">+₱{{ number_format($addOn->price, 2) }}</span>
                                </label>
                            @endforeach
                        </fieldset>
                        <button type="submit" class="toggle-button">Save my picks</button>
                    </form>
                @endif

                {{-- The cart entry point: pick add-ons and a quantity, and the
                     line total computes itself. Add to cart prompts the Google
                     button while signed out. --}}
                @if ($gate->canOrder)
                    <form class="add-to-cart" method="POST" action="{{ route('cart.update') }}">
                        @csrf
                        <input type="hidden" name="product" value="{{ $product->id }}">
                        @foreach ($selectedAddOns as $addOnName)
                            <input type="hidden" name="addOns[]" value="{{ $addOnName }}">
                        @endforeach
                        <label class="field field--inline">
                            <span class="field__label">Quantity</span>
                            <input class="field__input field__input--narrow" type="number" name="quantity" value="1" min="1" max="99">
                        </label>
                        <button type="submit" class="primary-button">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false" aria-hidden="true">
                                <path d="M3 4h2l2.4 11h10.2l2-7H6"/>
                                <circle cx="9" cy="19" r="1.4"/>
                                <circle cx="17" cy="19" r="1.4"/>
                            </svg>
                            Add to cart
                        </button>
                    </form>
                @endif
            </div>
        </article>
    </main>

    @include('menu.partials.footer')
</body>
</html>
