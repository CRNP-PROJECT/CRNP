@php
    /** @var array<string, \App\Rentals\RentalItem> $items */
    /** @var array<string, \App\Rentals\RentalItem> $lowStock */
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rental inventory · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    @include('admin.partials.nav')

    <main class="site-main">
        <h1>Rental inventory</h1>
        <p class="site-main__copy">
            Inventory is rental units only — menu items carry no stock. Stock rises
            via Add stock (a receiving entry) and Confirm return; it falls at
            handover. Adjust day to day with the inline − / + stepper.
        </p>

        @if (session('status'))
            <p class="notice" role="status">{{ session('status') }}</p>
        @endif

        <div class="action-row">
            <a class="primary-button" href="{{ route('admin.rentals.create') }}">Add a rental item</a>
        </div>

        @if ($items === [])
            <p class="empty-state">No rental items yet — add the first rentable item.</p>
        @else
            <table class="admin-table">
                <thead>
                    <tr>
                        <th scope="col">Item</th>
                        <th scope="col">Price / day</th>
                        <th scope="col">Units</th>
                        <th scope="col">Add stock</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr>
                            <td>
                                <span class="product-cell">
                                    @if ($item->imageBase64 !== '')
                                        <img class="product-thumb" src="data:image/jpeg;base64,{{ $item->imageBase64 }}" alt="">
                                    @endif
                                    <span>{{ $item->name }}</span>
                                </span>
                            </td>
                            <td>₱{{ number_format($item->pricePerDay, 2) }}</td>
                            <td>
                                {{-- Inline − / + stepper: one tap adjusts on-hand units. --}}
                                <span class="stock-stepper" role="group" aria-label="Units of {{ $item->name }}">
                                    <form method="POST" action="{{ route('admin.rentals.adjust', ['item' => $item->id]) }}" class="inline-form">
                                        @csrf
                                        <input type="hidden" name="delta" value="-1">
                                        <button type="submit" class="stepper__button" aria-label="Fewer units of {{ $item->name }}">−</button>
                                    </form>
                                    <span class="stepper__value">{{ $item->units }}</span>
                                    <form method="POST" action="{{ route('admin.rentals.adjust', ['item' => $item->id]) }}" class="inline-form">
                                        @csrf
                                        <input type="hidden" name="delta" value="1">
                                        <button type="submit" class="stepper__button" aria-label="More units of {{ $item->name }}">+</button>
                                    </form>
                                </span>
                            </td>
                            <td>
                                <form method="POST" action="{{ route('admin.rentals.stock', ['item' => $item->id]) }}" class="inline-form add-stock-form" data-add-stock>
                                    @csrf
                                    <input class="field__input field__input--narrow" type="text" name="supplier" placeholder="Supplier" aria-label="Supplier for {{ $item->name }}" required>
                                    <span class="stock-stepper" role="group" aria-label="Received quantity for {{ $item->name }}">
                                        <button type="button" class="stepper__button" data-stock-step="-1" aria-label="Fewer units for {{ $item->name }}">−</button>
                                        <input class="stepper__input" type="number" name="qty" min="1" step="1" value="1" aria-label="Quantity for {{ $item->name }}" required>
                                        <button type="button" class="stepper__button" data-stock-step="1" aria-label="More units for {{ $item->name }}">+</button>
                                    </span>
                                    <input class="field__input field__input--narrow" type="date" name="date" value="{{ $today }}" aria-label="Received date for {{ $item->name }}" required>
                                    <button type="submit" class="link-button">Add stock</button>
                                </form>
                            </td>
                            <td>
                                <a href="{{ route('admin.rentals.edit', ['item' => $item->id]) }}">Edit</a>
                                <form method="POST" action="{{ route('admin.rentals.destroy', ['item' => $item->id]) }}" class="inline-form" onsubmit="return confirm('Delete this rental item?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="link-button">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <section class="queue-section" aria-labelledby="low-stock-heading">
            <h2 class="queue-section__title" id="low-stock-heading">Low stock</h2>
            <p class="queue-section__hint">At or below {{ $threshold }} units — restock in one tap.</p>

            @if ($lowStock === [])
                <p class="empty-state">Nothing low on stock.</p>
            @else
                <ul class="queue-list">
                    @foreach ($lowStock as $item)
                        <li class="queue-row">
                            <span class="queue-row__code">{{ $item->name }}</span>
                            <span class="queue-row__total">{{ $item->units }} left</span>
                            <form method="POST" action="{{ route('admin.rentals.stock', ['item' => $item->id]) }}" class="queue-row__action">
                                @csrf
                                <input type="hidden" name="supplier" value="Restock">
                                <input type="hidden" name="qty" value="1">
                                <input type="hidden" name="date" value="{{ $today }}">
                                <button type="submit" class="primary-button">Restock</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </main>

    <script>
        // The Add stock quantity is a stepper: the − / + buttons adjust the
        // number input, never submitting until Add stock is tapped.
        (function () {
            var forms = document.querySelectorAll('[data-add-stock]');

            Array.prototype.forEach.call(forms, function (form) {
                var field = form.querySelector('input[name="qty"]');

                Array.prototype.forEach.call(form.querySelectorAll('[data-stock-step]'), function (button) {
                    button.addEventListener('click', function () {
                        var step = parseInt(button.getAttribute('data-stock-step'), 10) || 0;
                        var value = parseInt(field.value, 10) || 1;
                        field.value = Math.max(1, value + step);
                    });
                });
            });
        })();
    </script>
</body>
</html>
