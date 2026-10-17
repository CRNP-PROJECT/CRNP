{{--
    The register header: it names the cashier on duty from `staff/{uid}` and
    offers Switch account — sign out, then the one Google sign-in button — so a
    shared register still records known hands.
--}}
<header class="register-header">
    <span class="register-header__brand">{{ config('app.name') }} · POS</span>
    <nav class="register-header__nav" aria-label="Register">
        <a class="register-header__link" href="{{ route('cashier.queue') }}" @if (request()->routeIs('cashier.queue')) aria-current="page" @endif>Online queue</a>
        <a class="register-header__link" href="{{ route('cashier.reservations.index') }}" @if (request()->routeIs('cashier.reservations.*')) aria-current="page" @endif>Reservations</a>
        <a class="register-header__link" href="{{ route('cashier.rentals.index') }}" @if (request()->routeIs('cashier.rentals.*')) aria-current="page" @endif>Rentals</a>
    </nav>
    <p class="register-header__duty">
        <span class="register-header__duty-label">Cashier on duty</span>
        <span class="register-header__duty-name">{{ $cashier?->name ?? 'Signed in' }}</span>
    </p>
    <form method="POST" action="{{ route('cashier.logout') }}" class="register-header__switch">
        @csrf
        <button type="submit" class="link-button">Switch account</button>
    </form>
</header>
