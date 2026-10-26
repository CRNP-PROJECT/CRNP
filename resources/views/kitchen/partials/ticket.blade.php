{{--
    One kitchen ticket. The server stamps every clock and flag; the client only
    ticks the age timer (`data-age-seconds` on the card) and the LATER countdown
    (`data-ready-in`) from those origins. A promoted scheduled ticket shows BOTH
    clocks — its age timer and its ready-for instant — and reads LATE immediately
    when overdue. The server owns the lane, so the cook never drags a card.
--}}
@php
    $nowSeconds = now('UTC')->getTimestamp();
    $classes = 'kitchen-card';
    $classes .= $ticket->isNow() ? '' : ' kitchen-card--later';
    $classes .= ($ticket->isNow() && $ticket->promoted) ? ' kitchen-card--promoted' : '';
    $classes .= $ticket->red ? ' kitchen-card--red' : '';
    $classes .= $ticket->late ? ' kitchen-card--late' : '';
@endphp

<li
    class="{{ $classes }}"
    data-ticket="{{ $ticket->id }}"
    data-lane="{{ $ticket->lane }}"
    data-ready-for="{{ $ticket->readyFor }}"
    @if ($ticket->isNow())
        data-age-seconds="{{ $ticket->ageSeconds }}"
    @else
        data-ready-in="{{ $ticket->readyInSeconds($nowSeconds) }}"
    @endif
>
    <header class="kitchen-card__head">
        <span class="kitchen-card__code">{{ $ticket->code }}</span>

        @if ($ticket->new)
            <span class="kitchen-badge kitchen-badge--new">NEW</span>
        @endif

        @if ($ticket->late)
            <span class="kitchen-badge kitchen-badge--late">LATE</span>
        @endif
    </header>

    @if ($ticket->lines !== [])
        <ul class="kitchen-card__lines">
            @foreach ($ticket->lines as $line)
                <li class="kitchen-card__line">
                    <span class="kitchen-card__qty">{{ $line->quantity }}×</span>
                    <span class="kitchen-card__name">{{ $line->name }}</span>
                    @if ($line->addOns !== [])
                        <ul class="kitchen-card__addons">
                            @foreach ($line->addOns as $addOn)
                                <li class="kitchen-card__addon">{{ $addOn->name }}</li>
                            @endforeach
                        </ul>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    <footer class="kitchen-card__clocks">
        @if ($ticket->isNow())
            <span class="kitchen-card__clock kitchen-card__clock--age @if ($ticket->red) is-red @endif">
                <span class="kitchen-card__clock-label">Time</span>
                <span class="kitchen-card__clock-value">{{ $ticket->ageClock() }}</span>
            </span>

            {{-- A promoted scheduled ticket carries a second clock: its
                 ready-for instant, so lateness is never hidden by a fresh age. --}}
            @if ($ticket->promoted)
                <span class="kitchen-card__clock kitchen-card__clock--ready">
                    <span class="kitchen-card__clock-label">Ready for</span>
                    <span class="kitchen-card__clock-value">
                        {{ \Illuminate\Support\Carbon::parse($ticket->readyFor)->timezone(config('settings.timezone'))->format('g:i A') }}
                    </span>
                </span>
            @endif
        @else
            <span class="kitchen-card__clock kitchen-card__clock--ready">
                <span class="kitchen-card__clock-label">Ready in</span>
                <span class="kitchen-card__clock-value">{{ \App\Kitchen\KitchenTicket::clock($ticket->readyInSeconds($nowSeconds)) }}</span>
            </span>
        @endif
    </footer>
</li>
