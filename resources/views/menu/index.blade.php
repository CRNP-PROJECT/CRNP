<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} · Menu</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    @include('menu.partials.header')

    <main class="site-main">
        @if ($banner !== null)
            <p class="announcement-banner" role="status">{{ $banner }}</p>
        @endif

        <section class="menu-hero" aria-labelledby="menu-heading">
            <div class="menu-hero__intro">
                <h1 id="menu-heading">Menu</h1>
                <p class="site-main__copy">
                    Order online for pickup — GCash-only. Browse freely; sign in
                    with Google when you are ready to order or save a favorite.
                </p>
            </div>
            @include('menu.partials.store-badge')
            @include('menu.partials.order-gate')
        </section>

        @if ($categories !== [])
            {{-- One compact dropdown instead of a chip per category: a long
                 menu stays tidy, and picking a category re-renders the groups
                 filtered to it. --}}
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
                            href="{{ route('home') }}"
                            @if ($selectedCategory === null) aria-current="true" @endif
                        >All Products</a>
                    </li>
                    @foreach ($categories as $id => $category)
                        <li>
                            <a
                                class="category-menu__item"
                                href="{{ route('home', ['category' => $id]) }}"
                                @if ($selectedCategory === $id) aria-current="true" @endif
                            >{{ $category->name }}</a>
                        </li>
                    @endforeach
                </ul>
            </details>
        @endif

        <div id="menu">
            @if ($groups === [])
                <section class="empty-state" aria-labelledby="menu-empty-heading">
                    <h2 class="empty-state__title" id="menu-empty-heading">The menu is being set up</h2>
                    <p>No products yet — the owner adds them from the back office.</p>
                </section>
            @else
                @foreach ($groups as $group)
                    <section class="menu-group" aria-labelledby="menu-group-{{ $group->id }}">
                        <h2 class="menu-group__title" id="menu-group-{{ $group->id }}">{{ $group->name }}</h2>
                        <div class="product-grid">
                            @foreach ($group->products as $product)
                                @include('menu.partials.product-card', ['product' => $product])
                            @endforeach
                        </div>
                    </section>
                @endforeach
            @endif
        </div>
    </main>

    @include('menu.partials.footer')
</body>
</html>
