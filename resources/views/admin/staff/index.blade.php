<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Staff · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    @include('admin.partials.nav')

    <main class="site-main">
        <h1>Staff</h1>
        <p class="site-main__copy">
            Each cashier and admin signs in with their own Google account — no
            passwords, no public signup. Demoting or deleting an account ends its
            access on the next request.
        </p>

        @if (session('status'))
            <p class="notice" role="status">{{ session('status') }}</p>
        @endif

        {{-- One primary action: create. Name + Google email + role. --}}
        <section class="staff-create" aria-labelledby="staff-create-heading">
            <h2 id="staff-create-heading">Add a staff account</h2>
            <form method="POST" action="{{ route('admin.staff.store') }}" class="staff-form">
                @csrf
                <label class="field">
                    <span class="field__label">Name</span>
                    <input class="field__input" type="text" name="name" value="{{ old('name') }}" required>
                    @error('name')<span class="field__error">{{ $message }}</span>@enderror
                </label>
                <label class="field">
                    <span class="field__label">Google email</span>
                    <input class="field__input" type="email" name="email" value="{{ old('email') }}" required>
                    @error('email')<span class="field__error">{{ $message }}</span>@enderror
                </label>
                <label class="field">
                    <span class="field__label">Role</span>
                    <select class="field__input" name="role" required>
                        @foreach ($roles as $role)
                            <option value="{{ $role->value }}" @selected(old('role') === $role->value)>
                                {{ $role->label() }}
                            </option>
                        @endforeach
                    </select>
                    @error('role')<span class="field__error">{{ $message }}</span>@enderror
                </label>
                <button type="submit" class="primary-button">Create staff</button>
            </form>
        </section>

        <section aria-labelledby="staff-list-heading">
            <h2 id="staff-list-heading">Staff accounts</h2>

            @if ($staff === [])
                <p class="empty-state">No staff accounts yet.</p>
            @else
                <table class="staff-table">
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Google email</th>
                            <th scope="col">Role</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($staff as $member)
                            <tr>
                                <td>{{ $member->name }}</td>
                                <td>{{ $member->email }}</td>
                                <td>
                                    <form method="POST" action="{{ route('admin.staff.update', ['staff' => $member->id]) }}" class="inline-form">
                                        @csrf
                                        @method('PUT')
                                        <label class="visually-hidden" for="role-{{ $member->id }}">Role for {{ $member->name }}</label>
                                        <select id="role-{{ $member->id }}" name="role" class="field__input">
                                            @foreach ($roles as $role)
                                                <option value="{{ $role->value }}" @selected($member->role === $role)>
                                                    {{ $role->label() }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="link-button">Save role</button>
                                    </form>
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('admin.staff.destroy', ['staff' => $member->id]) }}" class="inline-form">
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
