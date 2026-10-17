<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kitchen board · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    {{--
        The kitchen board — read-only by design: zero input, zero login, zero
        cook interaction. NOW is oldest-first with age timers (red at 12 min),
        the NEW badge (a genuine new ticket, never a promoted one) and all-day
        counts over NOW only; LATER holds dimmed scheduled pickups with live
        countdowns. The server owns lanes, promotion, and expiry; the client
        only ticks clocks and polls.

        The kiosk fetches the coalesced state every 5 s. A heartbeat banner
        ("Reconnecting…") appears once the board has gone ~15 s without a fresh
        response, and the page auto-reloads with backoff so a slept kiosk
        recovers with no human — a dead screen never looks live.
    --}}
    <main
        class="site-main kitchen"
        data-poll-url="{{ $pollUrl }}"
        data-poll-seconds="{{ $pollSeconds }}"
        data-stale-seconds="{{ $staleSeconds }}"
        data-signature="{{ $snapshot->signature() }}"
    >
        <header class="kitchen__head">
            <h1 class="kitchen__title">Kitchen board</h1>

            {{-- The reconnecting banner: hidden while the board is live, shown
                 once the heartbeat goes stale. A plain sentence, no spinner. --}}
            <p class="kitchen__reconnect" id="kitchen-reconnect" role="status" aria-live="polite" hidden>
                Reconnecting…
            </p>
        </header>

        <div class="kitchen__lanes">
            <section class="kitchen-lane kitchen-lane--now" aria-labelledby="kitchen-now-heading">
                <h2 class="kitchen-lane__title" id="kitchen-now-heading">
                    NOW <span class="kitchen-lane__count" data-count="now">{{ $snapshot->counts()['now'] }}</span>
                </h2>

                @if ($snapshot->now === [])
                    <p class="empty-state">Nothing cookable right now.</p>
                @else
                    <ul class="kitchen-list">
                        @foreach ($snapshot->now as $ticket)
                            @include('kitchen.partials.ticket', ['ticket' => $ticket])
                        @endforeach
                    </ul>
                @endif

                @if ($snapshot->allDay !== [])
                    <section class="kitchen-allday" aria-labelledby="kitchen-allday-heading">
                        <h3 class="kitchen-allday__title" id="kitchen-allday-heading">All day (NOW)</h3>
                        <ul class="kitchen-allday__list">
                            @foreach ($snapshot->allDay as $row)
                                <li class="kitchen-allday__row">
                                    <span class="kitchen-allday__name">{{ $row['name'] }}</span>
                                    <span class="kitchen-allday__qty">{{ $row['quantity'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </section>

            <section class="kitchen-lane kitchen-lane--later" aria-labelledby="kitchen-later-heading">
                <h2 class="kitchen-lane__title" id="kitchen-later-heading">
                    LATER <span class="kitchen-lane__count" data-count="later">{{ $snapshot->counts()['later'] }}</span>
                </h2>

                @if ($snapshot->later === [])
                    <p class="empty-state">No scheduled pickups waiting.</p>
                @else
                    <ul class="kitchen-list">
                        @foreach ($snapshot->later as $ticket)
                            @include('kitchen.partials.ticket', ['ticket' => $ticket])
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </main>

    <script>
        (function () {
            var root = document.querySelector('.kitchen');
            if (!root) { return; }

            var url = root.getAttribute('data-poll-url');
            var seconds = parseInt(root.getAttribute('data-poll-seconds'), 10) || 5;
            var staleSeconds = parseInt(root.getAttribute('data-stale-seconds'), 10) || 15;
            var signature = root.getAttribute('data-signature');
            var banner = document.getElementById('kitchen-reconnect');

            var reduceMotion = window.matchMedia
                && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            var lastOk = Date.now();
            var reloading = false;

            // Backoff across reloads: a kiosk that keeps failing doubles its
            // retry delay (capped at 30 s) instead of hammering a dead server,
            // and a single good poll resets it. Stored in sessionStorage so the
            // delay survives the reload itself.
            var backoffKey = 'kitchen-backoff:' + url;

            function backoffSeconds() {
                var stored = parseInt(window.sessionStorage.getItem(backoffKey) || '0', 10);
                return stored > 0 ? stored : 0;
            }

            function bumpBackoff() {
                var next = backoffSeconds() > 0 ? Math.min(backoffSeconds() * 2, 30) : 2;
                try { window.sessionStorage.setItem(backoffKey, String(next)); } catch (e) { /* private mode */ }
                return next;
            }

            function resetBackoff() {
                try { window.sessionStorage.removeItem(backoffKey); } catch (e) { /* private mode */ }
            }

            function stale() {
                return (Date.now() - lastOk) / 1000 > staleSeconds;
            }

            function showBanner(show) {
                if (banner) { banner.hidden = !show; }
            }

            // A clock tick: every second, advance each age timer and each LATER
            // countdown from its server-stamped origin, so a kiosk that slept
            // still shows real elapsed time. The heartbeat watches for staleness.
            function tick() {
                if (stale() && !reloading) {
                    showBanner(true);
                    // Auto-reload with backoff: a slept kiosk recovers with no
                    // human, and a dead screen never looks live.
                    reloading = true;
                    var delay = bumpBackoff() * 1000;
                    setTimeout(function () { window.location.reload(); }, delay);
                    return;
                }

                var cards = document.querySelectorAll('[data-age-seconds]');
                for (var i = 0; i < cards.length; i++) {
                    var card = cards[i];
                    var next = parseInt(card.getAttribute('data-age-seconds'), 10) + 1;
                    card.setAttribute('data-age-seconds', String(next));

                    var ageValue = card.querySelector('.kitchen-card__clock--age .kitchen-card__clock-value');
                    if (ageValue) { ageValue.textContent = formatClock(next); }

                    if (next >= 12 * 60) {
                        var ageClock = card.querySelector('.kitchen-card__clock--age');
                        if (ageClock) { ageClock.classList.add('is-red'); }
                        card.classList.add('kitchen-card--red');
                    }
                }

                var later = document.querySelectorAll('[data-ready-in]');
                for (var j = 0; j < later.length; j++) {
                    var row = later[j];
                    var remaining = parseInt(row.getAttribute('data-ready-in'), 10) - 1;
                    row.setAttribute('data-ready-in', String(remaining));

                    var readyValue = row.querySelector('.kitchen-card__clock--ready .kitchen-card__clock-value');
                    if (readyValue) { readyValue.textContent = formatClock(remaining); }
                }
            }

            function formatClock(totalSeconds) {
                if (totalSeconds < 0) { totalSeconds = 0; }
                var minutes = Math.floor(totalSeconds / 60);
                var secs = totalSeconds % 60;
                return minutes + ':' + (secs < 10 ? '0' : '') + secs;
            }

            // The NEW badge flashes for ~5 s on a genuine new ticket, then holds
            // steady — at or below three flashes a second (WCAG 2.2 SC 2.3.1),
            // and skipped entirely under prefers-reduced-motion.
            function flashNewBadges() {
                var badges = document.querySelectorAll('.kitchen-badge--new');
                for (var i = 0; i < badges.length; i++) {
                    if (reduceMotion) { continue; }
                    badges[i].classList.add('is-flashing');
                    (function (badge) {
                        setTimeout(function () { badge.classList.remove('is-flashing'); }, 5000);
                    })(badges[i]);
                }
            }

            function poll() {
                fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                    .then(function (response) { return response.ok ? response.json() : null; })
                    .then(function (state) {
                        if (!state) { return; }
                        lastOk = Date.now();
                        showBanner(false);
                        resetBackoff();
                        if (state.signature && state.signature !== signature) {
                            window.location.reload();
                        }
                    })
                    .catch(function () { /* a blip keeps the last known board */ })
                    .then(function () { window.setTimeout(poll, seconds * 1000); });
            }

            flashNewBadges();
            window.setInterval(tick, 1000);
            window.setTimeout(poll, seconds * 1000);
        })();
    </script>
</body>
</html>
