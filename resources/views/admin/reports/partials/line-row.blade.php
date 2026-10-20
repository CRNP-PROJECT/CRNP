{{--
    One "item sold" row on the historical-sales encoding form. Rendered twice:
    once per existing line and once inside the <template> the "+ Add item"
    button clones, so the two can never drift.

    The add-ons are drawn **server-side** — one group per product that offers
    any — so the picks are in the HTML and trackable even before any script
    runs; CSS reveals the group matching the row's chosen product and hides the
    rest. The admin picks an add-on by ticking its box, and the box name carries
    the line's form index so it posts as `lines[i][addOns][]`.

    @var int|string $i        the line's form index, or `__i__` in the template
    @var array<string, mixed> $row   the line's old input (empty in the template)
    @var array<string, \App\Catalog\Product> $products
--}}
@php
    $selected = (string) ($row['productId'] ?? '');
    $chosen = array_values((array) ($row['addOns'] ?? []));
@endphp

<div class="line-row" data-product="{{ $selected }}">
    <label class="field">
        <span class="visually-hidden">Product</span>
        <select class="field__input line-row__product" name="lines[{{ $i }}][productId]">
            <option value="">— pick a product —</option>
            @foreach ($products as $product)
                <option value="{{ $product->id }}" data-price="{{ $product->sellingPrice() }}" @selected($selected === $product->id)>{{ $product->name }}</option>
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

    {{-- Every product's add-ons that this row could show. CSS shows only the
         group whose `data-product` matches the row's selection. --}}
    @foreach ($products as $product)
        @if ($product->addOns !== [])
            <div
                class="line-row__addons"
                data-product="{{ $product->id }}"
                @if ($selected !== $product->id) hidden @endif
            >
                <span class="line-row__addons-label">Add-ons · {{ $product->name }}</span>
                @foreach ($product->addOns as $addOn)
                    <label class="line-row__addon-option">
                        <input
                            class="line-row__addon"
                            type="checkbox"
                            name="lines[{{ $i }}][addOns][]"
                            value="{{ $addOn->name }}"
                            data-price="{{ $addOn->price }}"
                            @checked(in_array($addOn->name, $chosen, true))
                        >
                        <span>{{ $addOn->name }}@if ($addOn->price > 0) (+₱{{ number_format($addOn->price, 2) }})@endif</span>
                    </label>
                @endforeach
            </div>
        @endif
    @endforeach
</div>
