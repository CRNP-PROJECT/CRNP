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
            <nav class="category-chips" aria-label="Filter by category">
                <a
                    class="category-chip {{ $selectedCategory === null ? 'category-chip--active' : '' }}"
                    href="{{ route('home') }}"
                    @if ($selectedCategory === null) aria-current="true" @endif
                >All Products</a>
                @foreach ($categories as $id => $category)
                    <a
                        class="category-chip {{ $selectedCategory === $id ? 'category-chip--active' : '' }}"
                        href="{{ route('home', ['category' => $id]) }}"
                        @if ($selectedCategory === $id) aria-current="true" @endif
                    >{{ $category->name }}</a>
                @endforeach
            </nav>
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
