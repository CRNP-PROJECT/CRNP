{{--
    The global site footer — brand, quick links, and visit details on a raised
    band, then a copyright bar. Shared by every public page so the chrome is
    identical everywhere; tool areas (admin, cashier, kitchen) keep their own.
--}}
<footer class="site-footer">
    <div class="site-footer__grid">
        <div class="site-footer__brand">
            <a class="site-footer__brand-link" href="{{ route('home') }}">
                <span class="logo-chip">
                    <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name') }} logo">
                </span>
                <span class="site-footer__name">{{ config('app.name') }}</span>
            </a>
            <p class="site-footer__tagline">A neighbourhood diner in Iloilo City serving fresh plates with warm local hospitality.</p>
            <a class="site-footer__social" href="{{ config('settings.facebook_url') }}" target="_blank" rel="noopener noreferrer">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" focusable="false" aria-hidden="true">
                    <path d="M22 12a10 10 0 1 0-11.6 9.9v-7H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.4h-1.2c-1.2 0-1.6.8-1.6 1.6V12h2.7l-.4 2.9h-2.3v7A10 10 0 0 0 22 12z"/>
                </svg>
                <span>Facebook</span>
            </a>
        </div>

        <nav class="site-footer__nav" aria-labelledby="site-footer-explore">
            <h2 class="site-footer__heading" id="site-footer-explore">Explore</h2>
            <ul class="site-footer__list">
                <li><a class="site-footer__link" href="{{ route('home') }}">Menu</a></li>
                <li><a class="site-footer__link" href="{{ route('occupancy') }}">Availability</a></li>
                <li><a class="site-footer__link" href="{{ route('rentals.index') }}">Rentals</a></li>
                <li><a class="site-footer__link" href="{{ route('about') }}">About</a></li>
            </ul>
        </nav>

        <nav class="site-footer__nav" aria-labelledby="site-footer-account">
            <h2 class="site-footer__heading" id="site-footer-account">My Account</h2>
            <ul class="site-footer__list">
                @auth
                    <li><a class="site-footer__link" href="{{ route('cart.show') }}">Cart</a></li>
                    <li><a class="site-footer__link" href="{{ route('orders.tracker') }}">My order</a></li>
                    <li><a class="site-footer__link" href="{{ route('rentals.mine') }}">My rentals</a></li>
                @else
                    <li><a class="site-footer__link" href="{{ route('login') }}">Sign in</a></li>
                @endauth
            </ul>
        </nav>

        <div class="site-footer__visit">
            <h2 class="site-footer__heading">Visit</h2>
            <ul class="site-footer__list">
                <li class="site-footer__detail">
                    <span class="site-footer__detail-icon">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false" aria-hidden="true">
                            <path d="M12 21s7-5.5 7-11a7 7 0 1 0-14 0c0 5.5 7 11 7 11z"/>
                            <circle cx="12" cy="10" r="2.5"/>
                        </svg>
                    </span>
                    <span>MHW4+QVX, Mabolo, Iloilo City Proper, Iloilo City, 5000 Iloilo</span>
                </li>
                <li class="site-footer__detail">
                    <span class="site-footer__detail-icon">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false" aria-hidden="true">
                            <circle cx="12" cy="12" r="9"/>
                            <path d="M12 7v5l3 2"/>
                        </svg>
                    </span>
                    <span>Mon–Sun · 10:00 AM – 11:00 PM</span>
                </li>
                <li class="site-footer__detail">
                    <span class="site-footer__detail-icon">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false" aria-hidden="true">
                            <rect x="3" y="5" width="18" height="14" rx="2"/>
                            <path d="m4 7 8 6 8-6"/>
                        </svg>
                    </span>
                    <a class="site-footer__link" href="mailto:cratesnplates@gmail.com">cratesnplates@gmail.com</a>
                </li>
                <li class="site-footer__detail">
                    <span class="site-footer__detail-icon">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false" aria-hidden="true">
                            <rect x="3" y="5" width="18" height="14" rx="2"/>
                            <path d="M3 10h18"/>
                        </svg>
                    </span>
                    <span>GCash exclusive payments</span>
                </li>
            </ul>
        </div>
    </div>

    <div class="site-footer__bar">
        <p class="site-footer__copy">&copy; {{ now()->year }} {{ config('app.name') }}. All rights reserved.</p>
        <p class="site-footer__note">Order online · pay by GCash · collect at the counter</p>
    </div>
</footer>
