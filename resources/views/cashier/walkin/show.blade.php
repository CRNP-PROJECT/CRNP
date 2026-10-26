<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Walk-in {{ $order->code }} · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    @include('cashier.partials.register-header')

    <main class="site-main">
        <header class="walkin-head">
            <div>
                <p class="walkin-head__label">Walk-in order code</p>
                <p class="walkin-head__code">{{ $order->code }}</p>
            </div>
            <a class="link-button" href="{{ route('cashier.register') }}">Back to register</a>
        </header>

        @if ($errors->any())
            <p class="notice notice--error" role="alert">{{ $errors->first() }}</p>
        @endif

        <div class="walkin-grid">
            {{-- Tap tiles: each adds one of the product at its computed selling
                 price. Promo prices come from the product, never re-typed. --}}
            <section class="walkin-menu" aria-labelledby="walkin-menu-heading">
                <h2 class="walkin-panel__title" id="walkin-menu-heading">Menu</h2>

                {{-- Filter the tiles as the cashier types, so a long menu never
                     needs a scroll to find one dish. --}}
                @if ($menu !== [])
                    <label class="field">
                        <span class="field__label">Search menu</span>
                        <input class="field__input" type="search" id="walkin-menu-search" placeholder="Type a dish or drink…" autocomplete="off">
                    </label>
                    <p class="field__hint" id="walkin-menu-search-status" role="status" aria-live="polite"></p>
                @endif

                @if ($categories !== [])
                    {{-- One compact dropdown instead of a chip per category: a
                         long menu stays tidy, and picking a category reloads
                         the tiles filtered to it. --}}
                    <details class="category-menu">
                        <summary class="category-menu__button">
                            <span class="category-menu__text">
                                <span class="category-menu__label">Category</span>
                                <span class="category-menu__value">{{ $selectedCategoryName ?? 'All Products' }}</span>
                            </span>
                            <svg class="category-menu__chevron" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                <path d="m6 9 6 6 6-6"/>
                            </svg>
                        </summary>
                        <ul class="category-menu__list">
                            <li>
                                <a
                                    class="category-menu__item"
                                    href="{{ route('cashier.walkins.show', ['walkin' => $order->id]) }}"
                                    @if ($selectedCategory === null) aria-current="true" @endif
                                >All Products</a>
                            </li>
                            @foreach ($categories as $id => $category)
                                <li>
                                    <a
                                        class="category-menu__item"
                                        href="{{ route('cashier.walkins.show', ['walkin' => $order->id, 'category' => $id]) }}"
                                        @if ($selectedCategory === $id) aria-current="true" @endif
                                    >{{ $category->name }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endif

                @if ($menu === [])
                    <p class="empty-state">
                        @if ($selectedCategory === null)
                            No menu items yet — the owner adds them from the back office.
                        @else
                            No items in this category — pick another chip or All Products.
                        @endif
                    </p>
                @else
                    <div class="tile-grid">
                        @foreach ($menu as $product)
                            {{-- The tile carries its product name so the menu search
                                 can filter the grid without a round trip, and its
                                 selling price so the order panel can price a
                                 ticked selection before it is sent. --}}
                            <form method="POST" action="{{ route('cashier.walkins.items.store', ['walkin' => $order->id]) }}" class="tile @if ($product->addOns !== []) tile--options @endif @if (! $order->isPending()) tile--locked @endif" data-name="{{ $product->name }}" data-price="{{ number_format($product->sellingPrice(), 2, '.', '') }}">
                                @csrf
                                <input type="hidden" name="product" value="{{ $product->id }}">
                                <input type="hidden" name="quantity" value="1">

                                @if ($product->addOns === [])
                                    <button type="submit" class="tile__tap" @disabled(! $order->isPending())>
                                        <span class="tile__name">{{ $product->name }}</span>
                                        <span class="tile__price">
                                            @if ($product->promo !== null)
                                                <s>₱{{ number_format($product->price, 2) }}</s>
                                                ₱{{ number_format($product->sellingPrice(), 2) }}
                                            @else
                                                ₱{{ number_format($product->price, 2) }}
                                            @endif
                                        </span>
                                    </button>
                                @else
                                    <span class="tile__name">{{ $product->name }}</span>
                                    <span class="tile__price">
                                        @if ($product->promo !== null)
                                            <s>₱{{ number_format($product->price, 2) }}</s>
                                            ₱{{ number_format($product->sellingPrice(), 2) }}
                                        @else
                                            ₱{{ number_format($product->price, 2) }}
                                        @endif
                                    </span>

                                    {{-- Per-product add-ons: tick one or more, and the
                                         server adds each price to this line. --}}
                                    <fieldset class="tile__addons">
                                        <legend class="tile__addons-title">Add-ons</legend>
                                        @foreach ($product->addOns as $addOn)
                                            <label class="tile__addon">
                                                <input type="checkbox" name="addOns[]" value="{{ $addOn->name }}" data-price="{{ number_format($addOn->price, 2, '.', '') }}" @disabled(! $order->isPending())>
                                                <span class="tile__addon-name">{{ $addOn->name }}</span>
                                                <span class="tile__addon-price">+₱{{ number_format($addOn->price, 2) }}</span>
                                            </label>
                                        @endforeach
                                    </fieldset>

                                    <button type="submit" class="tile__add" aria-label="Add {{ $product->name }} to order" @disabled(! $order->isPending())>Add to order</button>
                                @endif
                            </form>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="walkin-cart" aria-labelledby="walkin-cart-heading">
                <h2 class="walkin-panel__title" id="walkin-cart-heading">Order</h2>

                {{-- Service type lives here, on the open order: a change of
                     mind switches the type in place and keeps every line. --}}
                <div class="walkin-service">
                    <span class="walkin-service__label" id="service-type-label">Service type</span>
                    <form method="POST" action="{{ route('cashier.walkins.service-type', ['walkin' => $order->id]) }}">
                        @csrf
                        @method('PATCH')
                        <div class="segmented" role="group" aria-labelledby="service-type-label">
                            @foreach (\App\Orders\ServiceType::cases() as $service)
                                <label class="segmented__option">
                                    <input
                                        type="radio"
                                        name="serviceType"
                                        value="{{ $service->value }}"
                                        @checked($order->serviceType === $service)
                                        @disabled(! $order->isPending())
                                        onchange="this.form.submit()"
                                    >
                                    <span>{{ $service->label() }}</span>
                                </label>
                            @endforeach
                        </div>
                        @if ($order->isPending())
                            <noscript><button type="submit" class="link-button">Update service type</button></noscript>
                        @endif
                    </form>
                </div>

                {{-- Ticked add-ons surface here before they join the order: the
                     cashier sees the exact product, picks, and price, and one tap
                     transfers it. Nothing is charged until the transfer. --}}
                <div class="walkin-draft" id="walkin-draft" hidden>
                    <p class="walkin-draft__title" id="walkin-draft-title">Ready to add</p>
                    <ul class="walkin-draft__list" id="walkin-draft-list" aria-labelledby="walkin-draft-title"></ul>
                </div>

                @if ($order->lines === [])
                    <p class="empty-state">No items yet — tap a tile to start the order.</p>
                @else
                    <ul class="walkin-lines">
                        @foreach ($order->lines as $line)
                            <li class="walkin-line">
                                <span class="walkin-line__main">
                                    <span class="walkin-line__name">{{ $line->name }}</span>
                                    @if ($line->addOns !== [])
                                        <ul class="walkin-line__addons">
                                            @foreach ($line->addOns as $addOn)
                                                <li>+ {{ $addOn->name }} <span>₱{{ number_format($addOn->price, 2) }}</span></li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </span>
                                <span class="walkin-line__qty">{{ $line->quantity }}×</span>
                                <span class="walkin-line__total">₱{{ number_format($line->lineTotal(), 2) }}</span>
                                @if ($order->isPending())
                                    <form method="POST" action="{{ route('cashier.walkins.items.destroy', ['walkin' => $order->id, 'line' => $loop->index]) }}" class="inline-form">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="link-button">Remove</button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                <p class="walkin-total">
                    <span>Total</span>
                    <span class="walkin-total__amount">₱{{ number_format($order->total, 2) }}</span>
                </p>

                @if ($order->isPending())
                    {{-- The cashier picks the payment method first, then types only
                         the amounts that choice needs. The server builds the tender
                         and refuses a choice that cannot balance. --}}
                    @php($selectedMethod = old('method', \App\Orders\TenderMethod::Cash->value))
                    <form
                        method="POST"
                        action="{{ route('cashier.walkins.settle', ['walkin' => $order->id]) }}"
                        class="settle-form"
                        data-total="{{ number_format($order->total, 2, '.', '') }}"
                    >
                        @csrf

                        <fieldset class="settle-form__method">
                            <legend class="field__label">Payment method</legend>
                            <div class="segmented settle-form__segmented">
                                @foreach (\App\Orders\TenderMethod::cases() as $method)
                                    <label class="segmented__option">
                                        <input
                                            type="radio"
                                            name="method"
                                            value="{{ $method->value }}"
                                            @checked($selectedMethod === $method->value)
                                        >
                                        <span>{{ $method->label() }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>

                        <label class="field" data-payment-field="cash">
                            <span class="field__label" data-payment-cash-label>Cash handed over (₱)</span>
                            <input class="field__input" type="number" name="cash" step="0.01" min="0" value="{{ old('cash') }}">
                            @error('cash')<span class="field__error">{{ $message }}</span>@enderror
                        </label>

                        <label class="field" data-payment-field="gcash-amount">
                            <span class="field__label">GCash amount (₱)</span>
                            <input class="field__input" type="number" name="gcashAmount" step="0.01" min="0" value="{{ old('gcashAmount') }}">
                            @error('gcashAmount')<span class="field__error">{{ $message }}</span>@enderror
                        </label>

                        <label class="field" data-payment-field="gcash-ref">
                            <span class="field__label">GCash ref#</span>
                            <input class="field__input" type="text" name="gcashRef" value="{{ old('gcashRef') }}" maxlength="64">
                            @error('gcashRef')<span class="field__error">{{ $message }}</span>@enderror
                        </label>

                        <p class="settle-form__hint" data-payment-hint>
                            Enter the cash handed over; change is computed automatically.
                        </p>

                        <button type="submit" class="primary-button">Settle &amp; print receipt</button>
                    </form>
                @else
                    <a class="primary-button" href="{{ route('cashier.walkins.receipt', ['walkin' => $order->id]) }}">Print receipt</a>
                @endif
            </section>
        </div>
    </main>

    {{-- Filter the menu tiles as the cashier types: a long menu is typed down to
         the one dish instead of scrolled. The status line reads the count back,
         so an empty result is never a silently blank panel. --}}
    <script>
        (() => {
            const searchEl = document.getElementById('walkin-menu-search');
            const statusEl = document.getElementById('walkin-menu-search-status');

            if (!searchEl) {
                return;
            }

            const tiles = Array.from(document.querySelectorAll('.tile-grid .tile'));

            const filter = () => {
                const query = searchEl.value.trim().toLowerCase();
                let shown = 0;

                tiles.forEach((tile) => {
                    const matches = query === '' || (tile.dataset.name || '').toLowerCase().includes(query);

                    tile.hidden = !matches;

                    if (matches) {
                        shown += 1;
                    }
                });

                if (!statusEl) {
                    return;
                }

                statusEl.textContent = query === ''
                    ? ''
                    : shown === 0
                        ? 'No products match “' + searchEl.value.trim() + '”.'
                        : shown + (shown === 1 ? ' product found.' : ' products found.');
            };

            searchEl.addEventListener('input', filter);
        })();
    </script>

    {{-- The settle form shows only the fields the picked payment method needs:
         Cash → cash only; GCash → GCash amount + ref#; Cash + GCash → all
         three. The GCash amount prefills with the total and is cleared when
         the cashier switches away, so a split starts from a blank box. --}}
    <script>
        (function () {
            var form = document.querySelector('.settle-form');
            if (!form) { return; }

            var cash = form.querySelector('[data-payment-field="cash"]');
            var cashLabel = form.querySelector('[data-payment-cash-label]');
            var gcashAmount = form.querySelector('[data-payment-field="gcash-amount"]');
            var gcashRef = form.querySelector('[data-payment-field="gcash-ref"]');
            var gcashAmountInput = form.querySelector('input[name="gcashAmount"]');
            var hint = form.querySelector('[data-payment-hint]');
            var methods = form.querySelectorAll('input[name="method"]');
            var total = parseFloat(form.getAttribute('data-total') || '0');
            var autoFilled = false;
            var lastMethod = null;

            var copy = {
                cash: {
                    label: 'Cash handed over (₱)',
                    hint: 'Enter the cash handed over; change is computed automatically.'
                },
                gcash: {
                    label: 'Cash handed over (₱)',
                    hint: 'The full total is paid by GCash; confirm the amount and enter the reference number.'
                },
                split: {
                    label: 'Cash amount (₱)',
                    hint: 'Enter the cash and GCash amounts; together they must equal the total.'
                }
            };

            function toggle(field, visible) {
                if (!field) { return; }

                field.hidden = !visible;
                field.querySelectorAll('input').forEach(function (input) {
                    input.disabled = !visible;
                    input.required = visible;
                });
            }

            function sync() {
                var picked = form.querySelector('input[name="method"]:checked');
                var method = picked ? picked.value : 'cash';

                toggle(cash, method !== 'gcash');
                toggle(gcashAmount, method !== 'cash');
                toggle(gcashRef, method !== 'cash');

                var methodChanged = lastMethod !== null && lastMethod !== method;

                if (gcashAmountInput) {
                    if (method === 'gcash' && (methodChanged || gcashAmountInput.value === '' || autoFilled)) {
                        gcashAmountInput.value = total.toFixed(2);
                        autoFilled = true;
                    } else if (method !== 'gcash' && autoFilled) {
                        gcashAmountInput.value = '';
                        autoFilled = false;
                    }
                }

                lastMethod = method;

                var selected = copy[method] || copy.cash;
                if (cashLabel) { cashLabel.textContent = selected.label; }
                if (hint) { hint.textContent = selected.hint; }
            }

            if (gcashAmountInput) {
                gcashAmountInput.addEventListener('input', function () { autoFilled = false; });
            }

            methods.forEach(function (input) { input.addEventListener('change', sync); });
            sync();
        })();
    </script>

    {{-- The order panel mirrors the ticks as they happen: every tile with a
         ticked add-on shows the product, its picks, and the computed price, and
         its Transfer button submits that tile — the same request the tile's Add
         button sends. Untick everything and the card leaves again, so nothing is
         charged until the cashier transfers. --}}
    <script>
        (() => {
            const draft = document.getElementById('walkin-draft');
            const list = document.getElementById('walkin-draft-list');

            if (!draft || !list) {
                return;
            }

            const tiles = Array.from(document.querySelectorAll('.tile-grid .tile'));
            const money = (amount) => '₱' + amount.toFixed(2);

            const render = () => {
                list.replaceChildren();
                let count = 0;

                tiles.forEach((tile) => {
                    const picked = Array.from(tile.querySelectorAll('input[type="checkbox"]:checked'));

                    if (picked.length === 0) {
                        return;
                    }

                    const addOns = picked.map((box) => ({
                        name: box.value,
                        price: Number(box.dataset.price || 0),
                    }));
                    const total = addOns.reduce((sum, addOn) => sum + addOn.price, Number(tile.dataset.price || 0));

                    const item = document.createElement('li');
                    item.className = 'walkin-draft__item';

                    const head = document.createElement('p');
                    head.className = 'walkin-draft__head';

                    const name = document.createElement('span');
                    name.textContent = tile.dataset.name || '';

                    const price = document.createElement('span');
                    price.className = 'walkin-draft__price';
                    price.textContent = money(total);

                    head.append(name, price);
                    item.append(head);

                    const picks = document.createElement('ul');
                    picks.className = 'walkin-draft__addons';

                    addOns.forEach((addOn) => {
                        const row = document.createElement('li');
                        row.className = 'walkin-draft__addon';

                        const label = document.createElement('span');
                        label.textContent = '+ ' + addOn.name;

                        const amount = document.createElement('span');
                        amount.className = 'walkin-draft__addon-price';
                        amount.textContent = money(addOn.price);

                        row.append(label, amount);
                        picks.append(row);
                    });

                    item.append(picks);

                    const transfer = document.createElement('button');
                    transfer.type = 'button';
                    transfer.className = 'link-button';
                    transfer.textContent = 'Transfer to order';
                    transfer.addEventListener('click', () => tile.requestSubmit());

                    item.append(transfer);
                    list.append(item);
                    count += 1;
                });

                draft.hidden = count === 0;
            };

            tiles.forEach((tile) => {
                tile.querySelectorAll('input[type="checkbox"]').forEach((box) => {
                    box.addEventListener('change', render);
                });
            });

            render();
        })();
    </script>
</body>
</html>
