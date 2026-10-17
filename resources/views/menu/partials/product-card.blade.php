{{--
    A menu product card: image, name, price with the computed promo
    strike-through + "% off", the Top 3 tag when the ranking includes it, and
    the favorite heart. The heart posts to the gated toggle, so a signed-out tap
    lands on the one Google button.
--}}
<article class="product-card">
    <a class="product-card__media" href="{{ route('menu.product', ['product' => $product->id]) }}">
        @if ($product->imageBase64 !== '')
            <img class="product-card__image" src="data:image/jpeg;base64,{{ $product->imageBase64 }}" alt="{{ $product->name }}">
        @else
            <span class="product-card__placeholder" aria-hidden="true">{{ mb_substr($product->name, 0, 1) }}</span>
        @endif
    </a>

    <div class="product-card__body">
        <h3 class="product-card__name">
            <a href="{{ route('menu.product', ['product' => $product->id]) }}">{{ $product->name }}</a>
        </h3>

        <p class="product-card__price">
            @if ($product->promo !== null)
                <s>₱{{ number_format($product->price, 2) }}</s>
                <span class="product-card__price-now">₱{{ number_format($product->promo->promoPrice, 2) }}</span>
            @else
                <span class="product-card__price-now">₱{{ number_format($product->price, 2) }}</span>
            @endif
        </p>

        <p class="product-card__tags">
            @if ($product->promo !== null)
                <span class="tag tag--promo">{{ $product->promo->label() }}</span>
            @endif
            @if (in_array($product->id, $topThree, true))
                <span class="tag tag--top">Top 3</span>
            @endif
        </p>
    </div>

    <form class="product-card__favorite" method="POST" action="{{ route('favorites.toggle', ['product' => $product->id]) }}">
        @csrf
        @php($isFavorite = in_array($product->id, $favorites, true))
        <button
            type="submit"
            class="favorite-toggle"
            aria-pressed="{{ $isFavorite ? 'true' : 'false' }}"
            aria-label="{{ $isFavorite ? 'Remove from favorites' : 'Save to favorites' }}"
            title="{{ $isFavorite ? 'Saved' : 'Save' }}"
        >
            <span class="favorite-toggle__glyph" aria-hidden="true">{{ $isFavorite ? '♥' : '♡' }}</span>
        </button>
    </form>
</article>
