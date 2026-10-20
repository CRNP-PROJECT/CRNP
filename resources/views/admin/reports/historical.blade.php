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
                            @include('admin.reports.partials.line-row', ['i' => $i, 'row' => $row, 'products' => $products])
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
                @include('admin.reports.partials.line-row', ['i' => '__i__', 'row' => [], 'products' => $products])
            </template>
        @endif
    </main>

    @if ($products !== [])
        <script>
            (function () {
                const rows = document.getElementById('line-rows');
                const addButton = document.getElementById('line-add');
                const template = document.getElementById('line-row-template');
                const totalEl = document.getElementById('historical-total');

                if (!rows || !addButton || !template || !totalEl) {
                    return;
                }

                let counter = rows.querySelectorAll('.line-row').length;

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

                // The row ships one add-on group per product, all server-rendered;
                // show only the group for the chosen product, hide the rest, and
                // un-tick the hidden ones so a stray pick can't post.
                const showAddOns = (row) => {
                    const productId = row.querySelector('.line-row__product').value;

                    row.querySelectorAll('.line-row__addons').forEach((group) => {
                        const matches = group.dataset.product === productId && productId !== '';

                        group.hidden = !matches;

                        if (!matches) {
                            group.querySelectorAll('.line-row__addon:checked').forEach((box) => {
                                box.checked = false;
                            });
                        }
                    });
                };

                const wire = (row) => {
                    const select = row.querySelector('.line-row__product');
                    const price = row.querySelector('.line-row__price');

                    select.addEventListener('change', () => {
                        const option = select.options[select.selectedIndex];

                        if (option && option.dataset.price && price.value === '') {
                            price.value = option.dataset.price;
                        }

                        showAddOns(row);
                        recalc();
                    });

                    row.querySelector('.line-row__qty').addEventListener('input', recalc);
                    price.addEventListener('input', recalc);
                    row.querySelectorAll('.line-row__addon').forEach((box) => box.addEventListener('change', recalc));

                    row.querySelector('.line-row__remove').addEventListener('click', () => {
                        if (rows.querySelectorAll('.line-row').length > 1) {
                            row.remove();
                            recalc();
                        }
                    });

                    showAddOns(row);
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
