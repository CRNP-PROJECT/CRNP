<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Categories · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    @include('admin.partials.nav')

    <main class="site-main">
        <h1>Categories</h1>
        <p class="site-main__copy">
            Categories group the menu. The order here is the order customers see;
            a category still holding products cannot be deleted.
        </p>

        @if (session('status'))
            <p class="notice" role="status">{{ session('status') }}</p>
        @endif

        @if ($errors->has('category'))
            <p class="notice notice--error" role="alert">{{ $errors->first('category') }}</p>
        @endif

        {{-- One primary action: add. --}}
        <section class="staff-create" aria-labelledby="category-create-heading">
            <h2 id="category-create-heading">Add a category</h2>
            <form method="POST" action="{{ route('admin.categories.store') }}" class="staff-form">
                @csrf
                <label class="field">
                    <span class="field__label">Name</span>
                    <input class="field__input" type="text" name="name" value="{{ old('name') }}" required>
                    @error('name')<span class="field__error">{{ $message }}</span>@enderror
                </label>
                <label class="field">
                    <span class="field__label">Order</span>
                    <input class="field__input" type="number" name="order" min="0" value="{{ old('order', 0) }}" required>
                    @error('order')<span class="field__error">{{ $message }}</span>@enderror
                </label>
                <button type="submit" class="primary-button">Add category</button>
            </form>
        </section>

        <section aria-labelledby="category-list-heading">
            <h2 id="category-list-heading">Menu categories</h2>

            @if ($categories === [])
                <p class="empty-state">No categories yet — add the first one.</p>
            @else
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Order</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($categories as $category)
                            <tr>
                                <td colspan="2">
                                    <form method="POST" action="{{ route('admin.categories.update', ['category' => $category->id]) }}" class="inline-form">
                                        @csrf
                                        @method('PUT')
                                        <label class="visually-hidden" for="name-{{ $category->id }}">Category name</label>
                                        <input id="name-{{ $category->id }}" class="field__input" type="text" name="name" value="{{ $category->name }}" required>
                                        <label class="visually-hidden" for="order-{{ $category->id }}">Category order</label>
                                        <input id="order-{{ $category->id }}" class="field__input field__input--narrow" type="number" name="order" min="0" value="{{ $category->order }}" required>
                                        <button type="submit" class="link-button">Save</button>
                                    </form>
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('admin.categories.destroy', ['category' => $category->id]) }}" class="inline-form" onsubmit="return confirm('Delete this category?');">
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
        </section>
    </main>
</body>
</html>
