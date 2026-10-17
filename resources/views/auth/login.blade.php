<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <header class="site-header">
        <span class="logo-chip">
            <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name') }} logo">
        </span>
        <span class="site-title">{{ config('app.name') }}</span>
    </header>

    <main class="site-main">
        <section class="sign-in" aria-labelledby="sign-in-heading">
            <h1 class="sign-in__title" id="sign-in-heading">Sign in to continue</h1>
            <p class="sign-in__copy">
                Ordering, favoriting, and saving your preferences need an account.
                Browsing stays open — no password, no one-time code, no email to verify.
            </p>
            @if (session('status'))
                <p class="sign-in__copy" role="status">{{ session('status') }}</p>
            @endif
            @include('auth.partials.google-button', ['redirectUrl' => $redirectUrl])
        </section>
    </main>
</body>
</html>
