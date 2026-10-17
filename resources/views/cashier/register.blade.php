<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    @include('cashier.partials.register-header')

    <main class="site-main">
        <section class="register-actions" aria-labelledby="register-heading">
            <h1 class="register-actions__title" id="register-heading">Ring up a walk-in</h1>
            <p class="site-main__copy">
                Tap tiles to build the order — promo prices and totals compute
                themselves. The order code is drawn at ring-up and the kitchen
                ticket appears only at settle.
            </p>

            <form method="POST" action="{{ route('cashier.walkins.store') }}">
                @csrf
                <label class="field">
                    <span class="field__label">Table name (optional)</span>
                    <input class="field__input" type="text" name="table" value="{{ old('table') }}" maxlength="60" placeholder="e.g. Table 4">
                    @error('table')<span class="field__error">{{ $message }}</span>@enderror
                </label>
                <button type="submit" class="primary-button">New walk-in</button>
            </form>
        </section>
    </main>
</body>
</html>
