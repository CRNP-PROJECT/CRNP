<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Encode historical sales · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    @include('admin.partials.nav')

    <main class="site-main">
        <h1>Encode historical sales</h1>

        <p class="site-main__copy">
            Backfill sales that happened before the system went live. Each entry
            is recorded in the sales ledger on the date you pick, so it appears
            in the Reports and Dashboard for that day. Leave a unit price blank
            to use the product's current price.
        </p>

        @if (session('status'))
            <p class="notice" role="status">{{ session('status') }}</p>
        @endif

        @if ($products === [])
            <p class="empty-state">Add a product first — a sale needs at least one line.</p>
        @else
            <form method="POST" action="{{ route('admin.historical.store') }}" class="historical-form">
                @csrf

                <label class="field">
                    <span class="field__label">Sale date</span>
                    <input class="field__input" type="date" name="date" max="{{ $today }}" value="{{ old('date', $today) }}" required>
                    @error('date')<span class="field__error">{{ $message }}</span>@enderror
                </label>

                <fieldset class="field">
                    <legend class="field__label">Payment method</legend>
                    <div class="segmented" role="group" aria-label="Payment method">
                        @foreach ($methods as $method)
                            <label class="segmented__option">
                                <input type="radio" name="method" value="{{ $method->value }}" @checked(old('method', 'cash') === $method->value)>
                                <span>{{ $method->label() }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('method')<span class="field__error">{{ $message }}</span>@enderror
                </fieldset>

                <fieldset class="line-set">
                    <legend class="field__label">Items sold</legend>

                    <div id="line-rows" class="line-rows">
                        @php
                            $rows = old('lines', [['productId' => '', 'quantity' => 1, 'unitPrice' => '']]);
                        @endphp

                        @foreach ($rows as $i => $row)
                            <div class="line-row">
                                <label class="field">
                                    <span class="visually-hidden">Product</span>
                                    <select class="field__input line-row__product" name="lines[{{ $i }}][productId]">
                                        <option value="">— pick a product —</option>
                                        @foreach ($products as $product)
                                            <option value="{{ $product->id }}" data-price="{{ $product->sellingPrice() }}" @selected(($row['productId'] ?? '') === $product->id)>{{ $product->name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="field">
                                    <span class="visually-hidden">Quantity</span>
                                    <input class="field__input line-row__qty" type="number" min="1" step="1" name="lines[{{ $i }}][quantity]" value="{{ $row['quantity'] ?? 1 }}" placeholder="Qty">
                                </label>
                                <label class="field">
                                    <span class="visually-hidden">Unit price</span>
                                    <input class="field__input line-row__price" type="number" min="0" step="0.01" name="lines[{{ $i }}][unitPrice]" value="{{ $row['unitPrice'] ?? '' }}" placeholder="₱ each (optional)">
                                </label>
                                <button type="button" class="link-button line-row__remove">Remove</button>

                                <div class="line-row__addons" data-line="{{ $i }}" data-chosen="{{ json_encode(array_values((array) ($row['addOns'] ?? []))) }}"></div>
                            </div>
                        @endforeach

                        @error('lines')<span class="field__error">{{ $message }}</span>@enderror
                        @error('lines.*.productId')<span class="field__error">{{ $message }}</span>@enderror
                        @error('lines.*.quantity')<span class="field__error">{{ $message }}</span>@enderror
                        @error('lines.*.unitPrice')<span class="field__error">{{ $message }}</span>@enderror
                        @error('lines.*.addOns.*')<span class="field__error">{{ $message }}</span>@enderror
                    </div>

                    <button type="button" class="link-button" id="line-add">+ Add item</button>
                </fieldset>

                <p class="historical-total" role="status" aria-live="polite">
                    Entry total: <strong id="historical-total">₱0.00</strong>
                </p>

                <div class="action-row">
                    <button type="submit" class="primary-button">Encode sale</button>
                </div>
            </form>

            <template id="line-row-template">
                <div class="line-row">
                    <label class="field">
                        <span class="visually-hidden">Product</span>
                        <select class="field__input line-row__product" name="lines[__i__][productId]">
                            <option value="">— pick a product —</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}" data-price="{{ $product->sellingPrice() }}">{{ $product->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field">
                        <span class="visually-hidden">Quantity</span>
                        <input class="field__input line-row__qty" type="number" min="1" step="1" name="lines[__i__][quantity]" value="1" placeholder="Qty">
                    </label>
                    <label class="field">
                        <span class="visually-hidden">Unit price</span>
                        <input class="field__input line-row__price" type="number" min="0" step="0.01" name="lines[__i__][unitPrice]" placeholder="₱ each (optional)">
                    </label>
                    <button type="button" class="link-button line-row__remove">Remove</button>

                    <div class="line-row__addons" data-line="__i__"></div>
                </div>
            </template>
        @endif
    </main>

    @if ($products !== [])
        @php
            // productId => list of {name, price} — the add-ons each product
            // offers, so the script can show the right checkboxes per line.
            $addOnMap = [];
            foreach ($products as $product) {
                $addOnMap[$product->id] = array_map(
                    static fn ($addOn): array => ['name' => $addOn->name, 'price' => $addOn->price],
                    $product->addOns,
                );
            }
        @endphp

        <script type="application/json" id="product-addons">{!! json_encode($addOnMap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>

        <script>
            (function () {
                const rows = document.getElementById('line-rows');
                const addButton = document.getElementById('line-add');
                const template = document.getElementById('line-row-template');
                const totalEl = document.getElementById('historical-total');
                const addOnEl = document.getElementById('product-addons');

                if (!rows || !addButton || !template || !totalEl || !addOnEl) {
                    return;
                }

                let counter = rows.querySelectorAll('.line-row').length;

                let addOnMap = {};
                try {
                    addOnMap = JSON.parse(addOnEl.textContent) || {};
                } catch (e) {
                    addOnMap = {};
                }

                const money = (amount) => '₱' + amount.toFixed(2);

                // The add-on prices ticked on one row, summed.
                const addOnTotal = (row) => {
                    let sum = 0;

                    row.querySelectorAll('.line-row__addon').forEach((box) => {
                        if (box.checked) {
                            const value = parseFloat(box.dataset.price);
                            if (!Number.isNaN(value)) {
                                sum += value;
                            }
                        }
                    });

                    return sum;
                };

                const recalc = () => {
                    let total = 0;

                    rows.querySelectorAll('.line-row').forEach((row) => {
                        const price = parseFloat(row.querySelector('.line-row__price').value);
                        const quantity = parseInt(row.querySelector('.line-row__qty').value, 10);

                        if (!Number.isNaN(price) && !Number.isNaN(quantity)) {
                            total += (price + addOnTotal(row)) * quantity;
                        }
                    });

                    totalEl.textContent = money(Math.round(total * 100) / 100);
                };

                // Rebuild one row's add-on checkboxes for the product it now
                // shows, keeping any picks the product still offers.
                const renderAddOns = (row) => {
                    const select = row.querySelector('.line-row__product');
                    const group = row.querySelector('.line-row__addons');
                    const productId = select.value;
                    const offered = addOnMap[productId] || [];

                    // Seed from the server-side picks (a re-render after a
                    // validation bounce), then fold in whatever is ticked now.
                    const chosen = new Set();

                    if (group.dataset.chosen) {
                        try {
                            JSON.parse(group.dataset.chosen).forEach((name) => chosen.add(name));
                        } catch (e) { /* ignore */ }
                    }

                    group.querySelectorAll('.line-row__addon:checked').forEach((box) => chosen.add(box.value));

                    // The line names its add-ons under its own form index.
                    const lineKey = group.dataset.line;
                    delete group.dataset.chosen;

                    group.innerHTML = '';

                    if (offered.length === 0) {
                        group.hidden = true;
                        return;
                    }

                    const legend = document.createElement('span');
                    legend.className = 'line-row__addons-label';
                    legend.textContent = 'Add-ons';
                    group.appendChild(legend);

                    offered.forEach((addOn) => {
                        const label = document.createElement('label');
                        label.className = 'line-row__addon-option';

                        const box = document.createElement('input');
                        box.type = 'checkbox';
                        box.className = 'line-row__addon';
                        box.name = 'lines[' + lineKey + '][addOns][]';
                        box.value = addOn.name;
                        box.dataset.price = String(addOn.price);
                        box.checked = chosen.has(addOn.name);
                        box.addEventListener('change', recalc);

                        const text = document.createElement('span');
                        text.textContent = addOn.price > 0 ? addOn.name + ' (+' + money(addOn.price) + ')' : addOn.name;

                        label.appendChild(box);
                        label.appendChild(text);
                        group.appendChild(label);
                    });

                    group.hidden = false;
                };

                const wire = (row) => {
                    const select = row.querySelector('.line-row__product');
                    const price = row.querySelector('.line-row__price');

                    select.addEventListener('change', () => {
                        const option = select.options[select.selectedIndex];

                        if (option && option.dataset.price && price.value === '') {
                            price.value = option.dataset.price;
                        }

                        renderAddOns(row);
                        recalc();
                    });

                    row.querySelector('.line-row__qty').addEventListener('input', recalc);
                    price.addEventListener('input', recalc);

                    row.querySelector('.line-row__remove').addEventListener('click', () => {
                        if (rows.querySelectorAll('.line-row').length > 1) {
                            row.remove();
                            recalc();
                        }
                    });

                    renderAddOns(row);
                };

                rows.querySelectorAll('.line-row').forEach(wire);

                addButton.addEventListener('click', () => {
                    const wrapper = document.createElement('div');
                    wrapper.innerHTML = template.innerHTML.replaceAll('__i__', String(counter++)).trim();

                    const row = wrapper.firstElementChild;
                    rows.appendChild(row);
                    wire(row);
                    recalc();
                });

                recalc();
            })();
        </script>
    @endif
</body>
</html>
