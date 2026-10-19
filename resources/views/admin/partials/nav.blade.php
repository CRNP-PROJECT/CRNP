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
        'reports' => ['admin.reports.sales', 'Reports'],
        'activity' => ['admin.activity', 'Activity'],
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
    <nav class="site-nav" aria-label="Admin">
        @foreach ($links as [$route, $label])
            @php
                $isActive = collect($prefixes[$route] ?? [$route])
                    ->contains(fn (string $prefix): bool => str_starts_with($current, $prefix));
            @endphp
            <a class="site-nav__link" href="{{ route($route) }}" @if ($isActive) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <form method="POST" action="{{ route('admin.logout') }}" class="site-account__switch">
        @csrf
        <button type="submit" class="link-button">Sign out</button>
    </form>
</header>
