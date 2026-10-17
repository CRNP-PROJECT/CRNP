{{--
    The public site header — the one logo on its dark chip, the always-visible
    labeled nav (never an unlabeled icon), and the account control. The logo is
    served as-is; it is white-on-transparent, so the chip keeps it legible.

    On narrow screens the nav and account fold into a drawer that the checkbox
    reveals — no JavaScript, and the labeled links still carry the meaning.
--}}
<header class="site-header">
    <a class="site-brand" href="{{ route('home') }}">
        <span class="logo-chip">
            <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name') }} logo">
        </span>
        <span class="site-title">{{ config('app.name') }}</span>
    </a>

    <input class="site-nav-toggle" type="checkbox" id="site-nav-toggle">
    <label class="site-nav-toggle__button" for="site-nav-toggle">
        <span class="visually-hidden">Menu</span>
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" focusable="false" aria-hidden="true">
            <path d="M4 7h16M4 12h16M4 17h16"/>
        </svg>
    </label>

    <div class="site-menu">
        <nav class="site-nav" aria-label="Primary">
            <a class="site-nav__link" href="{{ route('home') }}" @if (request()->routeIs('home')) aria-current="page" @endif>Menu</a>
            <a class="site-nav__link" href="{{ route('occupancy') }}" @if (request()->routeIs('occupancy')) aria-current="page" @endif>Availability</a>
            <a class="site-nav__link" href="{{ route('rentals.index') }}" @if (request()->routeIs('rentals.index', 'rentals.show')) aria-current="page" @endif>Rentals</a>
            <a class="site-nav__link" href="{{ route('about') }}" @if (request()->routeIs('about')) aria-current="page" @endif>About</a>
            @auth
                <a class="site-nav__link" href="{{ route('cart.show') }}" @if (request()->routeIs('cart.show', 'checkout.show')) aria-current="page" @endif>Cart</a>
                <a class="site-nav__link" href="{{ route('orders.tracker') }}" @if (request()->routeIs('orders.*')) aria-current="page" @endif>My order</a>
                <a class="site-nav__link" href="{{ route('rentals.mine') }}" @if (request()->routeIs('rentals.mine', 'rentals.track')) aria-current="page" @endif>My rentals</a>
            @endauth
        </nav>
        <div class="site-account">
            @auth
                <span class="site-account__name">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" focusable="false" aria-hidden="true">
                        <circle cx="12" cy="8" r="3.5"/>
                        <path d="M5 20c1.2-3.2 3.8-5 7-5s5.8 1.8 7 5"/>
                    </svg>
                    {{ auth()->user()?->name ?? 'Signed in' }}
                </span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="link-button">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false" aria-hidden="true">
                            <path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"/>
                            <path d="M10 17l5-5-5-5M15 12H3"/>
                        </svg>
                        Sign out
                    </button>
                </form>
            @else
                @include('auth.partials.google-button')
            @endauth
        </div>
    </div>
</header>
