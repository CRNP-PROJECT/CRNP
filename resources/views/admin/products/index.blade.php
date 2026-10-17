<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Products · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    @include('admin.partials.nav')

    <main class="site-main">
        <h1>Products</h1>
        <p class="site-main__copy">
            Menu items carry no stock — the hide toggle is the 86 board. Images are
            resized on the server and stored as base64, so no raw upload reaches the
            database.
        </p>

        @if (session('status'))
            <p class="notice" role="status">{{ session('status') }}</p>
        @endif

        {{-- One primary action: add. --}}
        <div class="action-row">
            <a class="primary-button" href="{{ route('admin.products.create') }}">Add a product</a>
        </div>

        @if ($products === [])
            <p class="empty-state">No products yet — add the first menu item.</p>
        @else
            <table class="admin-table">
                <thead>
                    <tr>
                        <th scope="col">Product</th>
                        <th scope="col">Category</th>
                        <th scope="col">Price</th>
                        <th scope="col">Promo</th>
                        <th scope="col">On menu</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($products as $product)
                        <tr>
                            <td>
                                <span class="product-cell">
                                    @if ($product->imageBase64 !== '')
                                        <img class="product-thumb" src="data:image/jpeg;base64,{{ $product->imageBase64 }}" alt="">
                                    @endif
                                    <span>{{ $product->name }}</span>
                                </span>
                            </td>
                            <td>{{ $categories[$product->categoryId]->name ?? '—' }}</td>
                            <td>
                                @if ($product->promo !== null)
                                    <s>₱{{ number_format($product->price, 2) }}</s>
                                    ₱{{ number_format($product->promo->promoPrice, 2) }}
                                    <span class="tag tag--promo">{{ $product->promo->label() }}</span>
                                @else
                                    ₱{{ number_format($product->price, 2) }}
                                @endif
                            </td>
                            <td>
                                @if ($product->promo !== null)
                                    <form method="POST" action="{{ route('admin.products.promo.destroy', ['product' => $product->id]) }}" class="inline-form">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="link-button">Remove promo</button>
                                    </form>
                                @else
                                    <a href="{{ route('admin.products.edit', ['product' => $product->id]) }}">Add promo</a>
                                @endif
                            </td>
                            <td>
                                <form method="POST" action="{{ route('admin.products.visibility', ['product' => $product->id]) }}" class="inline-form">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="hidden" value="{{ $product->hidden ? '0' : '1' }}">
                                    <button type="submit" class="toggle-button" aria-pressed="{{ $product->hidden ? 'true' : 'false' }}">
                                        {{ $product->hidden ? 'Show' : 'Hide' }}
                                    </button>
                                </form>
                            </td>
                            <td>
                                <a href="{{ route('admin.products.edit', ['product' => $product->id]) }}">Edit</a>
                                <form method="POST" action="{{ route('admin.products.destroy', ['product' => $product->id]) }}" class="inline-form" onsubmit="return confirm('Delete this product?');">
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
    </main>
</body>
</html>
