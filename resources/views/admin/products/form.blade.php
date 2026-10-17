<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $product === null ? 'Add a product' : 'Edit product' }} · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    @include('admin.partials.nav')

    <main class="site-main">
        <h1>{{ $product === null ? 'Add a product' : 'Edit product' }}</h1>

        @if (session('status'))
            <p class="notice" role="status">{{ session('status') }}</p>
        @endif

        @if ($categories === [])
            <p class="empty-state">
                Add a category first — every product belongs to one.
            </p>
        @else
            {{-- The product form stays promo-free: a promo is added below. --}}
            <form
                method="POST"
                action="{{ $product === null ? route('admin.products.store') : route('admin.products.update', ['product' => $product->id]) }}"
                enctype="multipart/form-data"
                class="product-form"
            >
                @csrf
                @if ($product !== null)
                    @method('PUT')
                @endif

                <label class="field">
                    <span class="field__label">Name</span>
                    <input class="field__input" type="text" name="name" value="{{ old('name', $product?->name) }}" required>
                    @error('name')<span class="field__error">{{ $message }}</span>@enderror
                </label>

                <label class="field">
                    <span class="field__label">Description</span>
                    <textarea class="field__input" name="description" rows="3">{{ old('description', $product?->description) }}</textarea>
                    @error('description')<span class="field__error">{{ $message }}</span>@enderror
                </label>

                <label class="field">
                    <span class="field__label">Price (₱)</span>
                    <input class="field__input" type="number" name="price" step="0.01" min="0.01" value="{{ old('price', $product?->price) }}" required>
                    @error('price')<span class="field__error">{{ $message }}</span>@enderror
                </label>

                <label class="field">
                    <span class="field__label">Category</span>
                    <select class="field__input" name="categoryId" required>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(old('categoryId', $product?->categoryId) === $category->id)>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('categoryId')<span class="field__error">{{ $message }}</span>@enderror
                </label>

                <label class="field">
                    <span class="field__label">{{ $product === null ? 'Image' : 'Replace image (optional)' }}</span>
                    <input class="field__input" type="file" name="image" accept="image/jpeg,image/png,image/webp" @required($product === null)>
                    @error('image')<span class="field__error">{{ $message }}</span>@enderror
                </label>

                <label class="field">
                    <span class="field__label">Theme (optional)</span>
                    <input class="field__input" type="text" name="theme" value="{{ old('theme', $product?->theme) }}" placeholder="e.g. matcha, caramel, mocha, strawberry, spanish-latte, amber">
                    <span class="field__error">@error('theme'){{ $message }}@enderror</span>
                </label>

                <fieldset class="addon-set">
                    <legend>Add-ons</legend>
                    <p class="field__label">A name the customer sees and the price it adds.</p>
                    @for ($i = 0; $i < 3; $i++)
                        <div class="addon-row">
                            <label class="field">
                                <span class="visually-hidden">Add-on name</span>
                                <input class="field__input" type="text" name="addOns[{{ $i }}][name]" value="{{ old("addOns.{$i}.name", ($product?->addOns[$i] ?? null)?->name) }}" placeholder="Name">
                            </label>
                            <label class="field">
                                <span class="visually-hidden">Add-on price</span>
                                <input class="field__input" type="number" step="0.01" min="0" name="addOns[{{ $i }}][price]" value="{{ old("addOns.{$i}.price", ($product?->addOns[$i] ?? null)?->price) }}" placeholder="₱">
                            </label>
                        </div>
                    @endfor
                    @error('addOns.*.name')<span class="field__error">{{ $message }}</span>@enderror
                    @error('addOns.*.price')<span class="field__error">{{ $message }}</span>@enderror
                </fieldset>

                <div class="action-row">
                    <button type="submit" class="primary-button">{{ $product === null ? 'Add product' : 'Save product' }}</button>
                    <a class="link-button" href="{{ route('admin.products.index') }}">Cancel</a>
                </div>
            </form>

            @if ($product !== null)
                <section class="promo-panel" aria-labelledby="promo-heading">
                    <h2 id="promo-heading">Promo</h2>
                    <p class="site-main__copy">
                        Type either a discount percent or an exact price — the sibling
                        value and the "% off" label compute themselves.
                    </p>

                    @if ($product->promo !== null)
                        <p class="promo-current">
                            Current: <s>₱{{ number_format($product->price, 2) }}</s>
                            ₱{{ number_format($product->promo->promoPrice, 2) }}
                            <span class="tag tag--promo">{{ $product->promo->label() }}</span>
                        </p>
                    @endif

                    <form method="POST" action="{{ route('admin.products.promo.store', ['product' => $product->id]) }}" class="promo-form">
                        @csrf
                        <div class="segmented" role="group" aria-label="Promo mode">
                            @foreach ($promoModes as $mode)
                                <label class="segmented__option">
                                    <input type="radio" name="mode" value="{{ $mode->value }}" @checked(old('mode', $product->promo?->mode?->value ?? $mode->value) === $mode->value)>
                                    <span>{{ $mode->unit() }}</span>
                                </label>
                            @endforeach
                        </div>

                        <label class="field">
                            <span class="field__label">Value</span>
                            <input
                                id="promo-value"
                                class="field__input"
                                type="number"
                                name="value"
                                step="0.01"
                                min="0"
                                value="{{ old('value') }}"
                                required
                            >
                            @error('value')<span class="field__error">{{ $message }}</span>@enderror
                        </label>

                        <p class="promo-preview" id="promo-preview" role="status" aria-live="polite">
                            Customer sees <s>₱{{ number_format($product->price, 2) }}</s>
                            <strong id="promo-preview-price">₱{{ number_format($product->promo?->promoPrice ?? $product->price, 2) }}</strong>
                            <span class="tag tag--promo" id="promo-preview-label">{{ $product->promo?->label() ?? '' }}</span>
                        </p>

                        <div class="action-row">
                            <button type="submit" class="primary-button">{{ $product->promo !== null ? 'Save promo' : 'Add promo' }}</button>
                            @if ($product->promo !== null)
                                <button type="submit" form="remove-promo" class="link-button">Remove promo</button>
                            @endif
                        </div>
                    </form>

                    @if ($product->promo !== null)
                        <form id="remove-promo" method="POST" action="{{ route('admin.products.promo.destroy', ['product' => $product->id]) }}">
                            @csrf
                            @method('DELETE')
                        </form>
                    @endif
                </section>
            @endif
        @endif
    </main>

    @if ($product !== null)
        <script>
            (function () {
                const price = {{ Js::from($product->price) }};
                const value = document.getElementById('promo-value');
                const previewPrice = document.getElementById('promo-preview-price');
                const previewLabel = document.getElementById('promo-preview-label');

                if (!value || !previewPrice || !previewLabel) {
                    return;
                }

                const money = (amount) => '₱' + amount.toFixed(2);

                const update = () => {
                    const mode = document.querySelector('input[name="mode"]:checked');
                    const entered = parseFloat(value.value);

                    if (!mode || Number.isNaN(entered)) {
                        return;
                    }

                    const promoPrice = mode.value === 'percent'
                        ? Math.round(price * (1 - entered / 100) * 100) / 100
                        : Math.round(entered * 100) / 100;
                    const percentOff = mode.value === 'percent'
                        ? Math.round(entered)
                        : (price > 0 ? Math.round((price - promoPrice) / price * 100) : 0);

                    previewPrice.textContent = money(promoPrice);
                    previewLabel.textContent = percentOff + '% off';
                };

                value.addEventListener('input', update);
                document.querySelectorAll('input[name="mode"]').forEach((radio) => {
                    radio.addEventListener('change', update);
                });
            })();
        </script>
    @endif
</body>
</html>
