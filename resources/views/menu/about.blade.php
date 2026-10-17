<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>About · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    @include('menu.partials.header')

    <main class="site-main about-page">
        <header class="about-page__header">
            <h1 class="about-page__title">About Us</h1>
            <p class="about-page__intro">A neighbourhood diner in Iloilo City serving fresh plates with warm local hospitality — order online, pay by GCash, and collect at the counter.</p>
        </header>

        {{-- Section 1 — three restaurant information cards. --}}
        <section class="about-cards" aria-label="Restaurant information">
            <article class="about-card">
                <span class="about-card__icons">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false" aria-hidden="true">
                        <rect x="3" y="5" width="18" height="14" rx="2"/>
                        <path d="m4 7 8 6 8-6"/>
                    </svg>
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false" aria-hidden="true">
                        <path d="M6.5 4h3l1.5 4-2 1.5a11 11 0 0 0 5 5L15.5 13l4 1.5v3a2 2 0 0 1-2 2A14 14 0 0 1 4.5 6a2 2 0 0 1 2-2z"/>
                    </svg>
                </span>
                <h2 class="about-card__title">Contact Details</h2>
                <p class="about-card__row"><span class="about-card__term">Email:</span> <a class="about-card__link" href="mailto:cratesnplates@gmail.com">cratesnplates@gmail.com</a></p>
                <p class="about-card__row"><span class="about-card__term">Phone:</span> <span>09XX XXX XXXX</span></p>
            </article>

            <article class="about-card">
                <span class="about-card__icons">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false" aria-hidden="true">
                        <circle cx="12" cy="12" r="9"/>
                        <path d="M12 7v5l3 2"/>
                    </svg>
                </span>
                <h2 class="about-card__title">Store Hours</h2>
                <p class="about-card__row">Mon–Sun · 10:00 AM – 11:00 PM</p>
                <p class="about-card__note">Open daily for pickup counter collections.</p>
            </article>

            <article class="about-card">
                <span class="about-card__icons">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false" aria-hidden="true">
                        <rect x="3" y="5" width="18" height="14" rx="2"/>
                        <path d="M3 10h18"/>
                    </svg>
                </span>
                <h2 class="about-card__title">Payment Method</h2>
                <p class="about-card__lead">GCash Exclusive</p>
                <p class="about-card__note">We accept online payments exclusively via GCash for quick and convenient pickup orders.</p>
            </article>
        </section>

        {{-- Section 2 — Connect With Us. --}}
        <section class="about-connect" aria-labelledby="about-connect-heading">
            <div class="about-connect__intro">
                <h2 class="about-page__heading" id="about-connect-heading">Connect With Us</h2>
                <p class="about-connect__copy">
                    <span class="about-connect__icon">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" focusable="false" aria-hidden="true">
                            <path d="M22 12a10 10 0 1 0-11.6 9.9v-7H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.4h-1.2c-1.2 0-1.6.8-1.6 1.6V12h2.7l-.4 2.9h-2.3v7A10 10 0 0 0 22 12z"/>
                        </svg>
                    </span>
                    <span>We accept online payments. Fresh quality ingredients and warm hospitality, with a unique local perspective. Discover delicious plates and convenient pickup options.</span>
                </p>
            </div>
            <a class="facebook-button" href="{{ config('settings.facebook_url') }}" target="_blank" rel="noopener noreferrer">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" focusable="false" aria-hidden="true">
                    <path d="M22 12a10 10 0 1 0-11.6 9.9v-7H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.4h-1.2c-1.2 0-1.6.8-1.6 1.6V12h2.7l-.4 2.9h-2.3v7A10 10 0 0 0 22 12z"/>
                </svg>
                <span>Facebook: {{ config('app.name') }}</span>
            </a>
        </section>

        {{-- Section 3 — customer reviews. --}}
        <section class="about-reviews" aria-labelledby="about-reviews-heading">
            <h2 class="about-page__heading" id="about-reviews-heading">Customer Reviews</h2>
            <div class="about-reviews__grid">
                <div class="rating-summary">
                    <p class="rating-summary__score">4.8</p>
                    <span class="rating-summary__stars" role="img" aria-label="4.8 out of 5 stars">
                        @for ($star = 0; $star < 5; $star++)
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" focusable="false" aria-hidden="true">
                                <path d="m12 3 2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.9 6.8 19.6l1-5.8L3.5 9.2l5.9-.9z"/>
                            </svg>
                        @endfor
                    </span>
                    <p class="rating-summary__caption">4.8 out of 5 rating</p>
                    <p class="rating-summary__note">Rated for fresh ingredients, honest food quality, and the warm hospitality our regulars come back for.</p>
                </div>

                <figure class="review-item">
                    <span class="review-item__avatar" aria-hidden="true">SL</span>
                    <div class="review-item__body">
                        <span class="review-item__name">Sarah L.</span>
                        <blockquote class="review-item__text">"Great quality, welcoming atmosphere, and convenient GCash payments."</blockquote>
                    </div>
                </figure>

                <figure class="review-item">
                    <span class="review-item__avatar" aria-hidden="true">MD</span>
                    <div class="review-item__body">
                        <span class="review-item__name">Mike D.</span>
                        <blockquote class="review-item__text">"Warm service, delicious food, and a welcoming atmosphere."</blockquote>
                    </div>
                </figure>

                <figure class="review-item">
                    <span class="review-item__avatar" aria-hidden="true">EP</span>
                    <div class="review-item__body">
                        <span class="review-item__name">Elena P.</span>
                        <blockquote class="review-item__text">"Great ingredients, friendly hospitality, and delicious food."</blockquote>
                    </div>
                </figure>
            </div>
        </section>

        {{-- Section 4 — a prominent left title over a balanced map + landmarks row. --}}
        <section class="about-location" aria-labelledby="about-location-heading">
            <h2 class="about-location__title" id="about-location-heading">Visit Us</h2>
            <div class="about-location__grid">
                <div class="about-map">
                    <iframe
                        class="about-map__frame"
                        src="https://www.google.com/maps?q=MHW4%2BQVX%2C%20Mabolo%2C%20Iloilo%20City%20Proper%2C%20Iloilo%20City%2C%205000%20Iloilo&amp;z=16&amp;output=embed"
                        title="Map showing {{ config('app.name') }} at MHW4+QVX, Mabolo, Iloilo City"
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                    ></iframe>
                </div>

                <div class="about-landmarks">
                    <h3 class="about-page__heading">Local Landmarks</h3>
                    <ul class="about-landmarks__list">
                        <li class="about-landmarks__item">
                            <span class="about-pin">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false" aria-hidden="true">
                                    <path d="M12 21s7-5.5 7-11a7 7 0 1 0-14 0c0 5.5 7 11 7 11z"/>
                                    <circle cx="12" cy="10" r="2.5"/>
                                </svg>
                            </span>
                            UPV Campus
                        </li>
                        <li class="about-landmarks__item">
                            <span class="about-pin">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false" aria-hidden="true">
                                    <path d="M12 21s7-5.5 7-11a7 7 0 1 0-14 0c0 5.5 7 11 7 11z"/>
                                    <circle cx="12" cy="10" r="2.5"/>
                                </svg>
                            </span>
                            Iloilo Doctors' Hospital
                        </li>
                        <li class="about-landmarks__item">
                            <span class="about-pin">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false" aria-hidden="true">
                                    <path d="M12 21s7-5.5 7-11a7 7 0 1 0-14 0c0 5.5 7 11 7 11z"/>
                                    <circle cx="12" cy="10" r="2.5"/>
                                </svg>
                            </span>
                            Casa Real de Iloilo
                        </li>
                    </ul>
                </div>
            </div>
        </section>
    </main>

    @include('menu.partials.footer')
</body>
</html>
