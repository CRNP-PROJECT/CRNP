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
                                 selection before it is sent. A tile with add-ons
                                 opens the order-card picker instead of growing a
                                 control panel on the menu. --}}
                            <form
                                method="POST"
                                action="{{ route('cashier.walkins.items.store', ['walkin' => $order->id]) }}"
                                class="tile @if ($product->addOns !== []) tile--options @endif @if (! $order->isPending()) tile--locked @endif"
                                data-name="{{ $product->name }}"
                                data-price="{{ number_format($product->sellingPrice(), 2, '.', '') }}"
                                @if ($product->addOns !== []) id="tile-form-{{ $product->id }}" @endif
                            >
                                @csrf
                                <input type="hidden" name="product" value="{{ $product->id }}">
                                <input type="hidden" name="quantity" value="1">

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
                                    @if ($product->addOns !== [])
                                        <span class="tile__badge">Add-ons</span>
                                    @endif
                                </button>

                                @if ($product->addOns !== [])
                                    {{-- The picker moves this list into the order
                                         card on a tile tap; the `form` attribute
                                         keeps every tick bound to this tile's
                                         form after the move. --}}
                                    <fieldset class="tile__addons">
                                        <legend class="tile__addons-title">Add-ons</legend>
                                        @foreach ($product->addOns as $addOn)
                                            <label class="tile__addon">
                                                <input type="checkbox" name="addOns[]" value="{{ $addOn->name }}" form="tile-form-{{ $product->id }}" data-price="{{ number_format($addOn->price, 2, '.', '') }}" @disabled(! $order->isPending())>
                                                <span class="tile__addon-name">{{ $addOn->name }}</span>
                                                <span class="tile__addon-price">+₱{{ number_format($addOn->price, 2) }}</span>
                                            </label>
                                        @endforeach
                                    </fieldset>

                                    <button type="submit" class="tile__add" @disabled(! $order->isPending())>Add to order</button>
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

                {{-- Add-ons live beside the running order, not on the menu: a tap
                     on a tile with options moves that product's add-on list into
                     this card, so the menu stays clean and the pick sits next to
                     the line it will join. --}}
                @if ($order->isPending())
                    <div class="walkin-picker" id="walkin-picker" role="group" aria-labelledby="walkin-picker-title" hidden>
                        <p class="walkin-picker__title" id="walkin-picker-title"><span id="walkin-picker-name"></span></p>
                        <div class="walkin-picker__slot" id="walkin-picker-slot"></div>
                        <p class="walkin-picker__total">
                            <span>Line total</span>
                            <span class="walkin-picker__amount" id="walkin-picker-total">₱0.00</span>
                        </p>
                        <div class="walkin-picker__actions">
                            <button type="submit" class="primary-button" id="walkin-picker-add">Add to order</button>
                            <button type="button" class="link-button" id="walkin-picker-cancel">Cancel</button>
                        </div>
                    </div>
                @endif

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
                    {{-- One Cash + GCash form: the cashier types the cash
                         received and the GCash amount (either may be 0), so the
                         saved legs still record which method each peso used. --}}
                    <form
                        method="POST"
                        action="{{ route('cashier.walkins.settle', ['walkin' => $order->id]) }}"
                        class="settle-form"
                    >
                        @csrf

                        <p class="settle-form__hint">
                            <strong>Payment method:</strong> Cash + GCash
                        </p>

                        <label class="field">
                            <span class="field__label">Cash received (₱)</span>
                            <input class="field__input" type="number" name="cash" step="0.01" min="0" value="{{ old('cash', '0') }}">
                            @error('cash')<span class="field__error">{{ $message }}</span>@enderror
                        </label>

                        <label class="field">
                            <span class="field__label">GCash amount (₱)</span>
                            <input class="field__input" type="number" name="gcashAmount" step="0.01" min="0" value="{{ old('gcashAmount', '0') }}">
                            @error('gcashAmount')<span class="field__error">{{ $message }}</span>@enderror
                        </label>

                        <label class="field">
                            <span class="field__label">GCash ref#</span>
                            <input class="field__input" type="text" name="gcashRef" value="{{ old('gcashRef') }}" maxlength="64">
                            @error('gcashRef')<span class="field__error">{{ $message }}</span>@enderror
                        </label>

                        <p class="settle-form__hint">
                            Enter 0 for the method not used. Change is computed from the cash received.
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

    {{-- The ref# is only needed when the cashier enters a GCash amount. The
         server enforces the same rule; this keeps the field honest before the
         submit. --}}
    <script>
        (function () {
            var form = document.querySelector('.settle-form');
            if (!form) { return; }

            var gcashAmount = form.querySelector('input[name="gcashAmount"]');
            var gcashRef = form.querySelector('input[name="gcashRef"]');

            if (!gcashAmount || !gcashRef) { return; }

            function sync() {
                gcashRef.required = parseFloat(gcashAmount.value || '0') > 0;
            }

            gcashAmount.addEventListener('input', sync);
            sync();
        })();
    </script>

    {{-- Add-on tiles open the order-card picker: the tile's own add-on list moves
         into the card, so the menu stays one clean tile per product. The picker's
         Add button submits the same tile form the tile would have submitted. --}}
    <script>
        (() => {
            const picker = document.getElementById('walkin-picker');
            const slot = document.getElementById('walkin-picker-slot');
            const nameEl = document.getElementById('walkin-picker-name');
            const totalEl = document.getElementById('walkin-picker-total');
            const addButton = document.getElementById('walkin-picker-add');
            const cancelButton = document.getElementById('walkin-picker-cancel');

            if (!picker || !slot || !nameEl || !totalEl || !addButton || !cancelButton) {
                return;
            }

            const money = (amount) => '₱' + amount.toFixed(2);
            const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const tiles = Array.from(document.querySelectorAll('.tile--options'));

            let openForm = null;
            let openFieldset = null;
            let openHome = null;

            const totalFor = (form, fieldset) => {
                const base = Number(form.dataset.price || 0);
                const extras = Array.from(fieldset.querySelectorAll('input[type="checkbox"]:checked'))
                    .reduce((sum, box) => sum + Number(box.dataset.price || 0), 0);

                return base + extras;
            };

            const syncTotal = () => {
                if (!openForm || !openFieldset) {
                    return;
                }

                totalEl.textContent = money(totalFor(openForm, openFieldset));
            };

            const close = () => {
                if (!openForm || !openFieldset || !openHome) {
                    return;
                }

                openFieldset.hidden = true;
                openHome.append(openFieldset);
                openForm = null;
                openFieldset = null;
                openHome = null;
                picker.hidden = true;
                addButton.removeAttribute('form');
            };

            const open = (form) => {
                const fieldset = form.querySelector('.tile__addons');

                if (!fieldset) {
                    return;
                }

                close();

                openForm = form;
                openFieldset = fieldset;
                openHome = fieldset.parentElement;

                fieldset.hidden = false;
                slot.append(fieldset);
                nameEl.textContent = form.dataset.name || '';
                addButton.setAttribute('form', form.id);

                syncTotal();
                picker.hidden = false;
                picker.scrollIntoView({ block: 'nearest', behavior: reduceMotion ? 'auto' : 'smooth' });

                const first = fieldset.querySelector('input[type="checkbox"]');

                if (first) {
                    first.focus({ preventScroll: true });
                }
            };

            tiles.forEach((form) => {
                const fieldset = form.querySelector('.tile__addons');
                const add = form.querySelector('.tile__add');

                if (!fieldset || !add) {
                    return;
                }

                // With script the picker owns the choices; without it the tile
                // keeps its inline list and Add button.
                fieldset.hidden = true;
                add.hidden = true;

                fieldset.querySelectorAll('input[type="checkbox"]').forEach((box) => {
                    box.addEventListener('change', syncTotal);
                });

                form.addEventListener('submit', (event) => {
                    if (event.submitter === addButton) {
                        return;
                    }

                    event.preventDefault();
                    open(form);
                });
            });

            cancelButton.addEventListener('click', close);
        })();
    </script>
</body>
</html>
