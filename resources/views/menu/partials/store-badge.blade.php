{{--
    The computed open/closed badge (#20's StoreStatus). A visitor sees the
    state at a glance; a scheduled closure carries its admin-written label.
--}}
<p class="store-badge {{ $gate->open ? 'store-badge--open' : 'store-badge--closed' }}" role="status">
    <span class="store-badge__dot" aria-hidden="true"></span>
    <span class="store-badge__label">{{ $gate->badge }}</span>
    @if ($gate->detail !== null)
        <span class="store-badge__detail">{{ $gate->detail }}</span>
    @endif
</p>
