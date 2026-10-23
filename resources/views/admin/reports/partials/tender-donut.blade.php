{{--
    A GCash-versus-cash donut, shared by the sales and payments reports.

    The ring is a conic gradient split at the GCash share; the hole carries the
    collected total. The legend prints each figure and percentage, so the colour
    is never the only signal.

    @var \App\Stats\PaymentMix $mix
--}}
@php
    $donutTotal = $mix->total();
    $gcashShare = $donutTotal > 0 ? ($mix->gcash / $donutTotal) * 100 : 0;
    $cashShare = $donutTotal > 0 ? ($mix->cash / $donutTotal) * 100 : 0;
@endphp

<figure class="donut">
    <div
        class="donut__ring"
        role="img"
        aria-label="GCash ₱{{ number_format($mix->gcash, 2) }} ({{ round($gcashShare) }}%), cash ₱{{ number_format($mix->cash, 2) }} ({{ round($cashShare) }}%)"
        style="background: conic-gradient(var(--color-brand) 0 {{ $gcashShare }}%, var(--color-border-strong) {{ $gcashShare }}% 100%)"
    >
        <div class="donut__hole">
            <span class="donut__total">₱{{ number_format($donutTotal, 2) }}</span>
            <span class="donut__caption">collected</span>
        </div>
    </div>
    <figcaption class="chart-legend">
        <span class="chart-legend__item">
            <span class="chart-legend__swatch chart-legend__swatch--gcash"></span>
            GCash · ₱{{ number_format($mix->gcash, 2) }} ({{ round($gcashShare) }}%)
        </span>
        <span class="chart-legend__item">
            <span class="chart-legend__swatch chart-legend__swatch--cash"></span>
            Cash · ₱{{ number_format($mix->cash, 2) }} ({{ round($cashShare) }}%)
        </span>
    </figcaption>
</figure>
