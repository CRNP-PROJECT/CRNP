@php
    /** @var \App\Rentals\RentalItem|null $item */
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $item === null ? 'Add rental item' : 'Edit rental item' }} · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    @include('admin.partials.nav')

    <main class="site-main">
        <h1>{{ $item === null ? 'Add a rental item' : 'Edit rental item' }}</h1>

        @if ($errors->any())
            <p class="notice notice--error" role="alert">{{ $errors->first() }}</p>
        @endif

        <form class="staff-form" method="POST" action="{{ $item === null ? route('admin.rentals.store') : route('admin.rentals.update', ['item' => $item->id]) }}" enctype="multipart/form-data">
            @csrf
            @if ($item !== null)
                @method('PUT')
            @endif

            <label class="field">
                <span class="field__label">Name</span>
                <input class="field__input" type="text" name="name" value="{{ old('name', $item?->name ?? '') }}" maxlength="120" required>
                @error('name')<span class="field__error">{{ $message }}</span>@enderror
            </label>

            <label class="field">
                <span class="field__label">Price per day (₱)</span>
                <input class="field__input" type="number" name="pricePerDay" step="0.01" min="0.01" value="{{ old('pricePerDay', $item?->pricePerDay ?? '') }}" required>
                @error('pricePerDay')<span class="field__error">{{ $message }}</span>@enderror
            </label>

            <label class="field">
                <span class="field__label">Units on hand</span>
                <input class="field__input" type="number" name="units" step="1" min="0" value="{{ old('units', $item?->units ?? 0) }}" required>
                @error('units')<span class="field__error">{{ $message }}</span>@enderror
            </label>

            <label class="field">
                <span class="field__label">Image {{ $item === null ? '' : '(leave blank to keep the current one)' }}</span>
                <input class="field__input" type="file" name="image" accept="image/*" @if ($item === null) required @endif>
                @error('image')<span class="field__error">{{ $message }}</span>@enderror
            </label>

            <div class="action-row">
                <button type="submit" class="primary-button">{{ $item === null ? 'Add item' : 'Save item' }}</button>
                <a class="link-button" href="{{ route('admin.rentals.index') }}">Cancel</a>
            </div>
        </form>
    </main>
</body>
</html>
