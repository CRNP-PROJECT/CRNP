{{--
    The place-order gate. When open and the payment block exists it is the one
    primary action; otherwise it is a disabled button carrying a plain reason
    (never an error code). The cart itself arrives with #23, so the enabled
    control links to the menu to start choosing.

    Icons are opt-in via `$ctaIcons` so the menu's markup stays icon-free.
--}}
@if ($gate->canOrder)
    @if ($ctaIcons ?? false)
        <a class="primary-button" href="#menu">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false" aria-hidden="true">
                <path d="M3 4h2l2.4 11h10.2l2-7H6"/>
                <circle cx="9" cy="19" r="1.4"/>
                <circle cx="17" cy="19" r="1.4"/>
            </svg>
            Start an order
        </a>
    @else
        <a class="primary-button" href="#menu">Start an order</a>
    @endif
@else
    <div class="order-gate">
        <button class="primary-button" type="button" disabled aria-disabled="true">Start an order</button>
        <p class="order-gate__reason">
            @if ($ctaIcons ?? false)
                <span class="order-gate__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false" aria-hidden="true">
                        <circle cx="12" cy="12" r="8.5"/>
                        <path d="M12 7.5V12l3 2"/>
                    </svg>
                </span>
            @endif
            {{ $gate->reason }}
        </p>
    </div>
@endif
