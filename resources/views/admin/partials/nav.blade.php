{{--
    The one admin header: every admin screen includes this, so the nav — and the
    sign-out — has a single source of truth. The active link is derived from the
    current route, so a screen cannot forget to pass `$active`.
--}}
@php
    use Illuminate\Support\Facades\Route;

    $current = Route::currentRouteName() ?? '';

    $links = [
        'dashboard' => ['admin.dashboard', 'Dashboard'],
        'reports' => ['admin.reports.sales', 'Sales'],
        'activity' => ['admin.activity', 'Activity'],
        'cashier-history' => ['admin.cashier.history', 'Cashier history'],
        'products' => ['admin.products.index', 'Products'],
        'categories' => ['admin.categories.index', 'Categories'],
        'inventory' => ['admin.rentals.index', 'Inventory'],
        'staff' => ['admin.staff.index', 'Staff'],
        'settings' => ['admin.settings.edit', 'Settings'],
    ];

    $prefixes = [
        'admin.dashboard' => ['admin.dashboard'],
        'admin.reports.sales' => ['admin.reports.'],
        'admin.activity' => ['admin.activity'],
        'admin.cashier.history' => ['admin.cashier.'],
        'admin.products.index' => ['admin.products.'],
        'admin.categories.index' => ['admin.categories.'],
        'admin.rentals.index' => ['admin.rentals.'],
        'admin.staff.index' => ['admin.staff.'],
        'admin.settings.edit' => ['admin.settings.'],
    ];

    // The historical-sales encoding tool: its link shows only while the feature
    // is enabled.
    if (config('settings.historical_sales_enabled')) {
        $links['historical'] = ['admin.historical.create', 'Encode sales'];
        $prefixes['admin.historical.create'] = ['admin.historical.'];
    }
@endphp
<header class="site-header">
    <span class="site-title">{{ config('app.name') }} · Admin</span>

    <input class="site-nav-toggle" type="checkbox" id="admin-nav-toggle">
    <label class="site-nav-toggle__button" for="admin-nav-toggle">
        <span class="visually-hidden">Menu</span>
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" focusable="false" aria-hidden="true">
            <path d="M4 7h16M4 12h16M4 17h16"/>
        </svg>
    </label>

    <div class="site-menu">
        <nav class="site-nav" aria-label="Admin">
            @foreach ($links as [$route, $label])
                @php
                    $isActive = collect($prefixes[$route] ?? [$route])
                        ->contains(fn (string $prefix): bool => str_starts_with($current, $prefix));
                @endphp
                <a class="site-nav__link" href="{{ route($route) }}" @if ($isActive) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
        <div class="site-account">
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit" class="link-button">Sign out</button>
            </form>
        </div>
    </div>
</header>
