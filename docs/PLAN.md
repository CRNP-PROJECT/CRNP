# Crates N' Plates Online Management System

> Living plan for the project, published as README for the team.
> Updated as decisions firm up.
> Last updated: 2026-09-23.

One web app that runs **Crates N' Plates Diner**: customers order
online for **pickup**, staff ring up walk-ins at the counter POS and
schedule reservations, the kitchen cooks off a read-only ticket
board, and the owner manages menu, inventory, staff, and reports.

**Contents** (GitHub's Outline icon jumps to any section):

- [1. Vision](#1-vision) — roles, surfaces, customer flow
- [2. Stack — decided](#2-stack--decided) — framework, database, hosting
- [3. Design system](#3-design-system) — click-first UX, density rules
- [4. Features](#4-features) — data, auth, ordering, POS, kitchen, admin

## 1. Vision

One web app that runs the diner online and in-store (full customer
flow below under [Customer flow](#customer-flow)). Capstone title says
**"Online Management System"** — it must run on the internet,
demonstrated end-to-end, not localhost-only.

| Area | URL prefix | Purpose |
|---|---|---|
| Customer | `/` | **Online ordering (pickup):** category menu → cart → place order → GCash QR auto-sent (proof auto-verified); product pages, about/branches, open/closed status, announcement banner, item tags, favorites; **rental catalog + booking** (per-date availability, prepaid); **Sign in with Google** required to order |
| Cashier/POS | `/cashier` | Walk-in orders, **split payment (GCash + cash)**, **online pickup queue** (auto-verify, mismatch flags → one-tap Approve/Reject, recently-verified spot-check, Dismissed / Unclaimed lists), **rental handover / return**, **reservation scheduling** (dine-in, function room, catering), receipts |
| Kitchen | `/kitchen` | **Read-only** ticket display: **NOW** (oldest-first, age timers, all-day) + **LATER** (scheduled pickups, auto-promoted) — no login, no cook interaction |
| Admin | `/admin` | Dashboard (sales analytics), products + inventory + rental items, staff, settings, reports |

Domain vocabulary (glossary: [CONTEXT.md](../CONTEXT.md)):

- **Orders** — two origins, one shared stream: **online** (pickup-only,
  GCash-only, identified by **order code**, no table field) and
  **walk-in** (cashier at the POS, split tender, table name added there).
- **Payment is the gate** — no cashier approval before the QR: placing
  the order auto-sends it (15-min payment window), and the uploaded
  GCash screenshot **auto-verifies** unless the customer's entered
  amount or GCash ref# mismatches (**flagged hold**). Default-yes:
  the cashier taps **Approve / Reject** only on a flag; one tap per
  order on the happy path (**Mark served**).
- **Dismissed** — payment window missed: active queue auto-clears, the
  order lands in a Dismissed list (Restore / Void), the customer's
  **order tracker** flips in-session.
- **Unclaimed** — paid but never collected by close: money kept,
  manual note (a close-sweep decision, never the clock's).
- **Kitchen display** — mirrors **verified, unserved** tickets
  read-only (order code on the ticket); clears when the cashier marks
  served or, server-owned, at ready-for + 15 min (→ the counter's
  ready-awaiting-handover row). Cook interaction: none — the server
  owns lane promotion and expiry (§4 kitchen board spec).
- **Reservations** — dine-in, function room, catering; staff-entered
  (a phone lookup prefills returning customers) on a centralized
  calendar with conflict and duplicate checks before confirm;
  customers get a read-only occupancy view, never a booking form.
- **Rentals** — a separate stream from orders: public catalog with
  computed per-date availability (stock vs overlapping confirmed
  bookings), booked + **prepaid** through the same GCash gate;
  **handover / return** move the stock, and the kitchen board never
  sees them (§4 *Rentals* spec).

Per-role flowcharts: customer →
[ordering](flow-charts/customer-ordering.html) ·
[payment](flow-charts/customer-payment.html) ·
[reservations & rentals](flow-charts/customer-rentals.html); cashier →
[online pickup](flow-charts/cashier.html) ·
[counter tasks](flow-charts/cashier-counter.html);
[kitchen](flow-charts/kitchen.html) ·
[admin](flow-charts/admin.html).

### Customer side — feature list

All at `/`. **Browse is open to everyone; ordering, favoriting, and
preference sync require Sign in with Google.**

**A. Browse — no login**

1. **Menu (landing)** — products grouped under category titles
   ("All Products"); promo prices struck through with computed "% off".
2. **Category filter** — tap a category chip → only that category.
3. **Product cards** — image, price, **promo** and **Top 3** tags
   (ranked live over the FIFO window), favorite
   heart (tapping while signed out prompts the Google button).
4. **Product page** — image, price/promo, details.
5. **Open/closed badge** — computed from admin hours per weekday +
   force-close toggle + scheduled date-range closures; place-order
   disabled while closed.
6. **Announcement banner** — admin-written, site-wide.
7. **About / branches**, reservation **occupancy view** (read-only),
   and **rental catalog** pages.
8. **Cart** — inline steppers, add-on picks, computed totals (the
   system does the math; the human types nothing).

**B. Account — one Google button**

9. **Sign in with Google** — no form, no OTP, no password; session
   remembered; browsing never gated.
10. **Favorites** — heart toggles per-account favorites, synced
    cross-device via `users/{uid}`.
11. **Saved add-on prefs** — a product's last add-on selection comes
    pre-checked next visit, on any device.

**C. Checkout — login required, 3 taps**

12. **Pickup time** — optional picker, **default = ASAP** (no typing);
    earliest selectable = now + 15 min; the picker refuses too-soon
    times, never an error after payment.
13. **Payment — GCash only** (no Pay at Counter online; paying before
    cooking is the no-show assurance). Place order → QR appears
    instantly in its **branded frame** (official image, never
    re-rendered) and the **15-min payment window** starts — no cashier
    approval in front of it.
14. **Place order** — POST to Laravel → validated, `throttle`d
    per-IP → **order code** returned (the code identifies the
    pickup; no table field).

**D. After ordering**

15. **Pay + prove** — placing the order opens the **order tracker**:
    a live status screen (*awaiting payment → verifying → cooking →
    READY*), polling every 5–10 s (no push, no websockets), bound to
    the session and `users/{uid}` — reopenable from the signed-in
    account, the **order code** shown, never typed. Customer pays
    GCash → takes the OS screenshot → taps **Attach payment
    screenshot**: the native picker opens on the newest image and
    selection auto-uploads (no Submit — a failure shows **"Upload
    failed — tap to retry"**) → **amount paid** (prefilled =
    order total) + **GCash ref#** typed → **auto-verified on
    arrival** when both match → kitchen ticket appears the same
    instant; a mismatch (or blank ref#) lands a **flagged hold**
    instead — both numbers side by side, cashier taps **Approve or
    Reject**, kitchen waits for the tap, and **Reject** flips the
    tracker to *"Payment needs checking — see the counter"* (Approve
    puts the ticket back by age). At 15:00 → **Dismissed only when no proof
    exists** — an upload started before the window closes wins the
    race and holds the order; only the zero-proof case flips the
    tracker to "Order dismissed — no payment received", auto-clears
    the cashier's queue, and lands the order in the **Dismissed list**
    (**Restore** re-arms the window and flips the tracker back /
    **Void**).
16. **Collect** — cooked → cashier **Mark served** (the single happy-
    path tap) → the tracker flips **cooking → READY** the same instant
    → customer collects with the **order code** → order feeds sales &
    analytics. Paid but never collected by close → **Unclaimed**
    (money kept — a close-sweep decision, never the board's timer),
    collectible later with **Collect late**.
    The tracker covers the active order only — no order history in v1.

### Customer flow

1. **Arrive** — `/` shows menu, open/closed badge, banner, tags,
   computed prices. No wall.
2. **Browse** — filter by category → product page → add to cart
   (steppers, add-ons; heart prompts sign-in if signed out).
3. **Order** — cart → *Sign in with Google* (once) → pickup time
   (default ASAP) → Place order → **GCash QR appears immediately**
   (auto-sent, 15-min window starts).
4. **Pay** — pay → OS screenshot → **Attach** (newest thumbnail,
   auto-uploads) → **auto-verified** → kitchen ticket same instant
   (scheduled pickup: dimmed in LATER, promoted at pickup − 15 min);
   a slow upload holds past 15:00 — only zero proof dismisses; the
   **order tracker** shows the live state (awaiting payment /
   verifying / cooking / dismissed).
5. **Collect** — cooked → cashier **Mark served** → the tracker
   flips **READY** → customer collects with the order code → shows in
   analytics.

## 2. Stack — decided

| Layer | Choice | Why |
|---|---|---|
| Framework | **Laravel 12 (PHP 8.2+)** | Routing, session auth, validation, queues, Blade — the boring default. |
| Views | **Blade + plain CSS (design tokens + component classes)** | Server-rendered, no SPA, no Inertia — and **zero build step**: no npm, no Vite, no Node anywhere. One `public/css/app.css`. |
| Motion | **CSS transitions + Web Animations API** | No animation library. Transitions for hover/toggle/focus, keyframes for toasts, native WAAPI for the rare choreography (badge bump, card stagger). Framer Motion (React-only) and `motion` both rejected — add a lib only if choreography proves painful. |
| Database | **Firebase Realtime Database, sole datastore** | One source of truth; no migrations while the schema churns; matches the declared capstone stack. Laravel does *not* use Eloquent/SQL — persistence goes through a thin RTDB service. |
| Firebase plan | **Spark (free) — 1 GB stored · 10 GB/mo downloaded (~360 MB/day) · 100 connections** | Capstone scale fits the free ceiling only under the read discipline in [§4 Data layer](#data-layer). On Spark an over-quota month **shuts RTDB off** — no throttling — so bandwidth is budgeted as a hard resource. Access is server-side REST (service account), and REST is excluded from the connection count, so the 100 cap never binds. |
| Auth — everyone | **Laravel session + Socialite — "Sign in with Google"** | Staff and customers share one provider: one button, zero signup/reset/verify screens, **no passwords anywhere**; `users/{uid}` for customers, `staff/{uid}` + role guards for staff (§4 *Auth & roles*). Basic scopes → Google's 100-user cap and app verification don't apply ([source](https://support.google.com/cloud/answer/15549945)) — publish the app anyway. New dep: `laravel/socialite` (approved). |
| Kitchen display | **Auto-refreshing read-only page (5 s fetch)** | One endpoint returning open orders. No websocket, no cook session — the route is gated by a shared-secret URL instead of a login. |
| Quality | Pint (Laravel's php-cs-fixer preset), PHPStan (larastan), Pest smoke tests | Lint, static analysis, offline smoke tests — one command each. |

<details>
<summary><b>Explicitly rejected</b> — recorded so they don't creep back in (expand)</summary>

- React / Inertia / Next.js — nothing needs them without a React animation lib.
- Tailwind / Vite / any build toolchain — the app needs one stylesheet, not a
  Node toolchain. Plain CSS with tokens + component classes; revisit only if
  hand-CSS becomes the bottleneck.
- Framer Motion / `motion` / any animation library — CSS + WAAPI covers it; revisit only with a concrete choreography requirement.
- SQL/Eloquent alongside Firebase — two sources of truth = sync bugs.
- Firebase Auth — second auth system beside Laravel's; Laravel session auth
  covers all roles. Google sign-in *is* now a requirement (client) and ships
  as **Socialite inside Laravel**, not Firebase Auth — stays rejected.
- Customer self-serve reservation portal — reservations are staff-entered;
  a portal would race the staff calendar for no promised requirement.
  Still rejected; the §4 public availability view is read-only and
  books nothing (it reverses nothing).
- Contact/chat/ask-questions — needs an inbox, moderation, and spam
  filtering or it rots; declined at the probe gate.
- Gmail API email — lost its last consumer (no OTP, no email receipts;
  receipts print at the counter). Re-add only when something must be emailed.
- Email/password customer accounts — no email stack → no password resets
  (dead-end accounts) and no verification; Google owns identity via
  Socialite instead. One button beats a form.
- Order status workflow / drag-and-drop ticket boards — cooked food is
  handed over at the counter; making the cook maintain columns/statuses is
  information tax (harsh-cook review: 2/10). The kitchen screen is
  read-only; cook interaction: none.
- WebSockets/Reverb — the kitchen page just fetches every 5 s; no push
  infrastructure.
- One RTDB read per device poll — N screens × 5 s × TLS overhead burns
  the free ~360 MB/day download ceiling within hours; Laravel
  coalesces polls behind one cached read instead (§4 *Quota
  discipline*).
- Cashier Approve-before-QR gate — a human tap between "I ordered" and
  "I can pay" with no purpose once payment is the gate; QR goes out at
  placement (cook review: kill it).
- Cook-side statuses / promotion toggles — lanes are promoted by the
  server (`pickup − 15 min` or verify), never by a cook tap.

</details>

## 3. Design system

### Brand

- **One official logo** — `public/images/logo.png` (498 × 501
  transparent PNG, carried over from `main`'s
  `assets/img/logo.png`): landing header, receipts, staff sign-in,
  favicon + social-preview seed. Served as-is — never re-rendered,
  never re-exported, the same rule the GCash QR gets. The artwork is
  white-on-transparent: in light mode it sits on a dark chip (surface
  token), never straight on the light background where it would
  disappear.

### Interaction

- Mobile-first breakpoints; cashier touch targets ≥44 px.
- Kitchen screen: **zero cook interaction** — no login, touch, or editing;
  shared-secret URL, auto-clears when the cashier marks served.
- Every destructive action gets a confirm (one reusable pattern, not three).
- Empty states designed, not default: "No orders waiting — line is clear."

### Click-first UX — everywhere (client, cashiers, cooks: non-technical)

Hard rule: **the system computes, the human only supplies values or
presses buttons.** No screen anywhere asks a person to do math, write a
label, or enter a code — promo prices, "% off" labels, stock totals,
booking conflicts, change due, open status, Top 3, receipts, report
totals: all derived.

Per surface:

- **Public site** — **browse is open**; **ordering requires sign-in**:
  one **Sign in with Google** button (Socialite — no form, no OTP,
  remembered session), then the 3-tap flow: cart picks → **pickup
  time** (default ASAP, picker refuses too-soon times) → Place order →
  the GCash QR appears in its branded frame automatically (15-min
  window). Hours, status, tags, Top 3, totals computed; favorites +
  saved add-on prefs sync per account; checkout route `throttle`d
  (anti-spam — accounts alone don't stop scripted sign-ups).
- **Cashier/POS** — tap tiles build the order; promo price, totals, and
  change compute themselves; payment is two tenders (GCash + cash,
  split allowed — cashier types one number, the other and the change
  compute themselves); receipt = one button; online queue runs
  **default-yes**: proof auto-verifies, only an amount/ref mismatch
  flags (**Approve / Reject** — the exception), **Mark served** is
  the one happy-path tap; Dismissed/Unclaimed lists absorb
  everything the system retires; reservation = the guided screen
  below.
- **Kitchen** — total by design: zero input, zero login, zero editing.
- **Admin** — buttons over forms: one **Add promo** button per product
  row → segmented `% / ₱` control + one number + live preview ("customer
  sees ~~₱250~~ **₱200**") → Save; remove = one click. Product form stays
  promo-free.
- **Reports** — one-click date ranges; totals and charts build
  themselves; no configuration.

Universal patterns:

- **≤3 inputs per screen.** Anything longer becomes a single-purpose
  guided screen (reservation: phone lookup prefills name/party/type →
  adjust date/time → confirm; brand-new customer gets the same task
  split ≤3 + ≤2 across two steps → conflict check runs itself).
- **Smart defaults pre-filled** (opening hours, receipt header,
  low-stock threshold): the human edits only what differs.
- **Default-yes, exception-only taps** — the system acts first and the
  human taps only to object (GCash proof auto-verifies; only an
  amount/ref mismatch flags — **Reject** is the exception). Target:
  **one tap per online order** (Mark served).
- **Inline steppers over edit forms** — stock adjust is `− / +` on the
  row, not a modal.
- **Every click confirms itself**: toast + row updates in place
  (destructive keeps the confirm rule above).

### Density — the "3 + 1" cozy rule (screen-by-screen)

Research backing: [docs/research/screen-density-2026.md](research/screen-density-2026.md)
(13 primary sources: NN/g progressive disclosure / Hick / content-to-chrome,
Lewis & Sauro 2024 clutter study, WCAG 2.2, Material + Carbon density models).

**The rule: ≤3 primary actions + 1 labeled overflow ("More…") per view.
Everything else lives exactly one disclosed step away — max 2 levels,
label carries clear information scent.**

Per-screen checklist (apply to every screen built):

1. **Earn its place** — on first paint, a control stays visible only if
   the current task needs it or staff use it daily. Else → overflow.
2. **Count commands, not content** — the budget counts actionable
   commands (save, cancel, add, edit, delete, nav). Content items that
   happen to be tappable (menu tiles, table rows) don't count, but must
   be grouped into sections/categories.
3. **≤9 visible commands per viewport** before grouping/overflow takes
   the rest. Every extra visible choice slows every decision (Hick).
4. **Squint test** — no two same-weight, same-style buttons in one
   visual group; hierarchy via size/contrast/grouping, not more buttons.
5. **Chrome never hides** — nav always visible and labeled; never an
   unlabeled icon (discover → recall → interaction-cost, NN/g).
6. **Floors** — targets ≥24×24 CSS px (WCAG 2.5.8); reflow at 320 px;
   overflow uses "Show more", never silent truncation (WCAG 1.4.10).
7. **Declutter order** — discard task-irrelevant content first, then
   reorganize the rest (grouping + whitespace); only then judge the
   screen dense (Lewis & Sauro 2024: clutter = content → discard,
   design → reorganize).

**Density by surface:** *comfortable* default (public site, cashier
POS); *compact* permitted only on read-only glanceable surfaces (kitchen
display, admin tables) and only after grouping/whitespace — mirroring
Material's three tiers and Carbon's per-screen model. "Dense" never
means sub-minimum tap targets.

## 4. Features

Grouped by area: a spec of what exists; nothing here implies an
order of work.

### Data layer

- **Firebase RTDB** is the sole datastore (§2). Thin models —
  **Order**, **Reservation**, **Rental booking**, **Product**,
  **Rental stock**, **Staff**, **Settings** — behind `RtdbClient` (service-account
  OAuth token cache); Laravel never touches Eloquent/SQL.
- **Three trees per order** — `orders/{id}` (status, money,
  timestamps — **never image bytes**), `proofs/{id}` (the screenshot
  only), and `sales/{id}` (**1:1 with the order**, written at *Mark
  served* and synthesized by the close sweep for *Unclaimed*; the
  shared key makes a double-write structurally impossible).
  `RtdbClient` stamps **`updatedAt`** on every write — RTDB has no
  native one, and both the sweeps and the export depend on it.
- Every query is declared in `database.rules.json` with `.indexOn`;
  all four cashier taps — **Approve, Reject, Mark served, Restore** —
  are status-guarded the same way (a stale or duplicate tap is a
  friendly no-op, never a last-write-wins flip); there is no stock to
  revert, menu items carry none; URLs come from `route()`; all times
  evaluate in `Asia/Manila` (server clock is UTC).
- **Proof lifecycle** — screenshots land compressed (client-side
  canvas on selection: adaptive JPEG, ≤300 KB target, ≥720 px
  legibility floor so receipt text stays readable; the server
  re-encodes to the same ceiling, never trusting the client) at
  **`proofs/{id}`**, a sibling of the order. List, queue, tracker,
  and board queries match `orders/{id}` only, so those reads are
  scalar **by construction** — RTDB has no field projection, so this
  is the only way the claim is true. The **daily end-of-day sweep**
  (`Asia/Manila`) **deletes** the node: on terminal orders (served /
  voided) **7 days** after `settledAt`, and on a **rejected** proof
  **7 days** after `rejectedAt` as watchdog evidence. The order row,
  the rejection record, and the sale are untouched — only the image
  goes.
- **The other two sweeps, named** (the plan refers to them; here is
  where they run):
  - **Expiry** — `everyMinute()` against a stored
    **`deadlineAt = placedAt + 15 min`** (`Asia/Manila`). Dismisses
    only orders that are **zero-proof *and* not mid-upload**. A
    **Restore re-arms `deadlineAt`** to a fresh 15 minutes, so a
    re-dismiss means a second real lapse, not a bug. Unlimited
    restores, each writing an audit entry (order id, who, when)
    shaped like the rejection record.
  - **Close** — at the weekday closing hour from settings (and on
    force-close): decides **Unclaimed**, **synthesizes the Unclaimed
    sale** into `sales/{id}`, and clears what is server-owned —
    never the board's timer.
- **Quota discipline** — the free ceiling (§2) allows ~**360 MB
  downloaded per day**, and *every* read Laravel makes counts toward
  it — TLS overhead and rules-denied requests included. Four standing
  rules keep polls, images, and exports inside it:
  1. **Coalesce polls** — every polled endpoint (kitchen 5 s, cashier
     queue 5–10 s, customer trackers 5–10 s) serves from a **Laravel
     cache entry whose TTL ≥ the client's poll interval**: N screens
     hit Laravel, Laravel hits RTDB at most once per interval.
     Freshness is unchanged — the entry is never older than the
     cadence the client already accepts. `file` cache driver, no new
     dependency.
  2. **Three cache entries, each evicted by its own writes** —
     - **catalog** (`/` menu + product pages, **60–300 s**) evicted
       only by admin **product *and settings*** writes — price, promo,
       visibility, category, add-ons, image, **and hours, force-close,
       scheduled closures, banner**. Product images are
       base64-in-RTDB and change only on an admin edit, so a page view
       never re-reads their bytes, and an emergency force-close
       propagates on the next request rather than after a TTL.
     - **`stats/top3`** its own small entry, evicted only when that
       node changes. **A sale never busts the menu** — otherwise every
       order during the rush would force a full base64 catalog
       re-read, and rule 2 would become the thing that breaks the
       quota.
     - **availability** — the public occupancy and per-date rental
       views at **60 s**, evicted by booking / rental / capacity /
       stock writes. Safe to be briefly stale because confirm
       re-checks the conflict server-side; the TTL only governs how
       long a customer may chase a slot that filled.
     TTLs are backstops for the nobody-edited case, never the
     propagation path. Site and register cannot disagree on a price
     (§4 *Customer site*): a customer is never shown a figure the POS
     won't charge.
  3. **Shallow, bounded reads** — list, board, and queue payloads are
     scalar because the images live in `proofs/` (above), never
     because a response was filtered after the fact: that download is
     already billed. Outside the export, no code path reads a whole
     node — every query is declared with `.indexOn` (above) and is
     range-, key-, or pagination-bounded. The export is the sole
     named exception, and only for the small config trees.
  4. **Incremental export** — the weekly backup reads every durable
     tree **by `updatedAt > lastRun`**, watermark and checkpoint held
     at `export/state`: `orders`, `sales`, `products`, `reservations`,
     and `rental bookings` (watermarked), plus the small config trees
     read whole (`staff`, `settings`, `rentals`, `stats`,
     `categories`). The
     **first run bootstraps in bounded pages of 500 rows**,
     checkpointed — never one whole-database read, which rule 3 bans
     and which at 800 MB alone costs ~3.5 GB/month: a third of the
     quota, every week it ran. **`proofs` is excluded** — a backup is
     a durable record, not an evidence archive: the rejection record
     already carries entered-vs-total, ref#, who, and when in text,
     and the images expire in 7 days regardless.
- Customer writes are **server-mediated through Laravel** (order POST,
  `users/{uid}` prefs) — never direct client writes.
- A rules fixture backs a Pest smoke test of the layer.

### Auth & roles

- **Every cashier has their own account** — created by the admin
  (name + Google email), no public staff signup, no OTP, **no
  passwords at all**: sign-in is **Google-only** for staff and
  customers alike, so there is nothing to issue, reset, or leak, and
  a Google account absent from the staff list is refused. The
  **first admin comes from a one-shot seeder**: `php artisan
  staff:seed --admin` reads name + Google email from environment
  variables, runs only while no staff row exists, and refuses to run
  again — no credentials in the repo, no installer route left to lock
  down later.
- **Role guards** are middleware per URL prefix (`/cashier`,
  `/admin`), and the role itself is **read per request** from
  `staff/{uid}` behind a short cache: deleting or demoting an account
  **evicts that entry immediately**, so access ends on the next
  request instead of at logout.
- **Rate limits — numbers, not vibes.** Checkout **10/min per
  signed-in user**, **30/min per real IP** for anonymous traffic,
  login **5 per 5 min per IP + email**. The IP is the *forwarded* one:
  trusted proxy ranges are configured, or nginx/Cloudflare collapses
  every customer into a single bucket and one bad login loop locks
  the whole counter out mid-shift.
- **Everyone signs in with Google via Socialite** (one button) —
  customers auto-provision `users/{uid}` in RTDB; the same button on
  `/cashier` and `/admin` resolves against `staff/{uid}` instead,
  which is what the role guards read.
- `/kitchen` is a **separate read-only route** gated by a
  shared-secret URL — no session, kiosk-level access (the thesis's
  "kitchen personnel" RBAC slot without a line-cook login). The secret
  lives in settings behind an admin **Rotate secret** button: the old
  URL dies on the press, so leaking it to a group chat is a same-day
  fix rather than a redeploy; the kiosk reloads with the new link.

### Customer site & ordering

All at `/`.

- **Landing / menu** — products grouped under category titles ("All
  Products"), computed promo strike-through and "% off", open/closed
  badge, announcement banner, item tags; about / branches page.
- **Categories + filter** — products carry an admin-managed category
  (Mains, Milktea Series, Budget Meal, …); tapping a category chip
  shows only that category.
- **Add-ons** — admin configures per-product add-ons (name + price);
  the customer picks them in the cart; the selection rides on the
  order line item through to kitchen and receipt.
- **Favorites + saved prefs** — the heart on a product card toggles a
  per-account favorite; each product's last add-on selection is
  re-checked next visit, on any device — stored at `users/{uid}`.
  These actions prompt sign-in; browsing never does. There is **no
  favorites page or filter in v1** — hearts persist and sync, nothing
  more.
- **Cart → checkout** — placing the order requires **Sign in with
  Google** (browse stays open; session must be live at POST). Online
  orders are **pickup-only, GCash-only** — no table field, no Pay at
  Counter option. Then, in order:
  - **pickup time** — optional picker; default = ASAP, earliest =
    now + 15 min; the picker refuses too-soon inputs, so no error can
    follow payment.
  - **place order** — validated, `throttle`d (§4 *Auth & roles*) and
    carries a **client-generated idempotency key** minted per checkout
    attempt: the server records `idem/{key} → orderId` and returns
    the **original order code** on replay, so a timeout-then-retry or
    a refresh cannot open a second 15-minute window (the button also
    disables after the first tap). **Order code** returned (it
    identifies the pickup; no table field) and the payment block is
    auto-sent — **both the GCash number and the official QR image**
    inside a branded frame, never re-rendered
    (`docs/research/gcash-qr-2026.md`) — starting the **15-min
    payment window** (`deadlineAt`). No cashier approval sits in front of it.
  - **order tracker** — the post-placement screen is a live status
    screen (5–10 s fetch, no websockets): *awaiting payment* →
    *verifying* → *cooking* → **READY** (flips the instant the cashier
    taps Mark served), plus *Dismissed* when the window lapses and
    **Payment rejected** when a proof is refused — *"Payment needs
    checking — see the counter"*, with the entered amount and the
    order total shown side by side beneath it: enough for the customer
    to self-correct, no blame and no reason codes. A **Restore**
    flips it back to *awaiting payment*. Bound
    to the session and `users/{uid}`: reopenable from the signed-in
    account with zero typing — the order code is displayed, never
    entered. Active order only; no order history.
  - **proof of payment** — GCash confirmation screenshot **attached
    from the tracker**: one **Attach payment screenshot** button →
    native picker opens on the **newest image** → selection
    **auto-uploads, no Submit** — with a visible failure state:
    spinner → **"Upload failed — tap to retry"** plus one automatic
    retry, and a request that is *sent with no terminal response*
    counts as **in flight** (so it holds the order across 15:00, per
    the expiry rule below — a customer is never dismissed for a
    connection they couldn't see failing) → two fields: **amount paid**
    (prefilled = order total; edit only what differs) + **GCash
    ref#** → **auto-verified on arrival** when amount = order total
    and ref# is present; anything else lands a **flagged hold**
    (cashier taps **Approve or Reject**; kitchen waits for the tap).
    The flag replaces the blanket "unconfirmed" 5-min watch: only
    mismatches reach a human, expected **≤ 2/shift**. ≤2 in-page
    taps + 2 fields (button + newest thumbnail; iOS's picker sheet
    adds one). Selection is **compressed client-side** first
    (adaptive JPEG: ≤300 KB, ≥720 px floor) and re-encoded to the
    same ceiling server-side — a 3 MB screenshot never reaches RTDB.
  - **expiry** — `deadlineAt` reached → **Dismissed only when no
    proof exists and none is in flight**: an upload started before
    the window closes wins the race and holds the order for verify.
    Zero proof flips the tracker in-session, auto-clears the cashier
    queue, and the Dismissed list keeps **Restore / Void**. **Restore
    re-arms `deadlineAt`** to a fresh 15 minutes and flips the tracker
    back to *awaiting payment*, so the customer simply re-attaches the
    same screenshot; a re-dismiss then means a second real lapse.
- **Open/closed badge** — computed from admin-configured hours per
  weekday, an admin **force-close override** (emergencies), and
  **scheduled date-range closures** that flip themselves on and off
  (holidays, configured once); place-order is disabled while closed —
  **and until the GCash number *and* the QR image are both
  configured**, the same disabled button carrying a plain reason and
  an admin **Complete setup** banner. You cannot send a payment block
  that doesn't exist yet; prepaid rentals sit behind the same gate.
- **Announcement banner** — admin-written promo text with a show/hide
  toggle in settings.
- **Item tags** (chips on product cards):
  - **promo** — admin enters *either* a discount percent *or* an
    exact promo price; the sibling value and the "% off" label are
    computed, never typed (stored as `promoMode` + `promoValue`,
    re-derived on any base-price edit, validated
    `0 < promoPrice < price`). The same fields drive the POS total —
    site and receipt can't disagree; the original shows struck
    through.
  - **Top 3** — **computed eagerly**: re-ranked server-side on every
    sale write (`sales/{id}`) over a rolling **7-day FIFO window**
    (raw sales rows are never deleted; analytics, reports, and the
    weekly export keep
    full history). **The ranking set is the revenue set — served +
    Unclaimed** — so the chip and the dashboard can never disagree.
    The ranking appears once **3 distinct products
    have a recorded sale** in the window — no waiting for a full week
    of history, no display gate. It always shows the current top ≤ 3,
    ranked by units sold with ties broken by revenue then name; empty
    window → no tag. The chip reads exactly **"Top 3"** — no "last 7
    days" suffix. Ranking is cached at `stats/top3` and read by
    the product-card render.

### Cashier POS

- **One register, known hands** — the header permanently names the
  **cashier on duty**; **Switch account** is sign-out → sign-in
  (Google, §4 *Auth & roles*): a shared register with an honest
  trail. Every write below is stamped **`handledBy`** (uid + name) —
  walk-in creation, **Mark served**, **Collect late**, and the sale
  row itself; the rejection record and the Restore audit entry
  already carry *who*. No money tap exists without a name behind it,
  and admin **Activity** reads them back (§4 *Admin*).
- Walk-in ring-up: tap tiles build the order; promo price, totals,
  and change compute themselves. The walk-in draws an **order code at
  ring-up** — the same code space as online orders, printed on the
  receipt — and the **kitchen ticket appears at settle**, not at the
  first tile tap: payment stays the gate, so no food is ever cooked
  for a walk-in who leaves the counter.
- **Split tender** — GCash + cash on one order. The cashier types
  **exactly one number: the cash handed over**; everything else
  derives — `cash ≥ total` → all-cash, `change = cash − total`, no
  GCash leg; `cash < total` → `GCash = total − cash`, change `0`, and
  a **GCash ref# is required whenever that leg is > 0** (walk-in GCash
  gets the same verification online orders have). The order stores
  `payments: [{method, amount}]` with **`Σ(payments) = total`
  asserted on write** — true by construction, still validated; the
  receipt prints the breakdown.
- **Online pickup queue** — orders arrive ready-to-pay (no approval
  tap — the QR went out at placement); the screenshot **auto-verifies**
  and the cashier taps **Reject** only on exception — the exception
  being a **flagged hold**: entered amount ≠ order total or blank
  ref#, shown with both numbers side by side for one-tap
  **Approve / Reject** (kitchen waits for the tap; expected
  **≤ 2/shift** — verified orders are never a watching duty).
  Verified orders land in a **recently-verified list** for at-leisure
  spot-check: no quota, no timer, **Reject stays reachable until
  Mark served**. In-window orders with **no screenshot yet** show as
  **awaiting proof**, so staff see a customer waiting at the counter
  instead of a blank queue.
- **Rejected is its own state**, not a variant of Dismissed. Reject
  writes the **rejection record** (order id, entered vs total, ref#,
  who, when), keeps the image **7 days** as evidence, and books
  **`refundOwed`** — the **entered amount, labelled *"claimed"*** and
  shown beside the order total, never a figure nobody verified (the
  GCash app decides what is actually owed; **Mark refunded** in
  admin settles it). The ticket **leaves the board the same
  instant** — the board mirrors *verified, unserved*. The customer's
  tracker reads *"Payment needs checking — see the counter"* with
  both numbers beneath it. From **Rejected** the only actions are
  **Approve** (the undo — the ticket **rejoins NOW by age**, timer
  never restarted, NEW badge never reused) or **Void**: never
  Restore, and the **close sweep leaves it alone**, so the owner
  finds it in the morning instead of it vanishing overnight.
- A proof upload that started before 15:00 **holds** the order — the
  expiry sweep dismisses only **zero-proof, not-mid-upload** orders.
  **Mark served** is the one happy-path tap: it flips the customer's
  **order tracker** to **READY** the same instant and **writes the
  sale** to `sales/{id}`.
- **Retired orders** — **Dismissed list**: **Restore** re-arms a
  fresh 15-minute window, flips the tracker back to *awaiting
  payment*, and writes an audit entry (order id, who, when) —
  unlimited restores, each an explicit human act; **Void** ends the
  order and never clears `refundOwed`. And **Unclaimed** (paid but
  never collected: money kept, manual note) gains **Collect late** —
  the customer appears with the code, the cashier taps it, the order
  flips to served; the sale was already synthesized at close, so the
  tap **cannot write a second one**.

### Kitchen board — read-only

Spec: the server owns everything, the cook owns nothing.

- **NOW lane** — oldest first: walk-ins + ASAP pickups. Walk-ins carry
  the **order code drawn at ring-up** (on the receipt), so one
  identifier type is on the board and the ticket only appears at
  settle. Per-ticket
  **age timers (red at 12 min)**, **NEW badge** (genuine new tickets
  only: flashes ~5 s, then a steady badge until the ticket ages past
  3 min), **all-day counts = NOW only** (never LATER — a cook must
  never be told to make food he's not allowed to make yet).
- **LATER** — scheduled pickups as full-size dimmed rows, sorted by
  ready-for time, live countdown. The server **promotes** at
  `pickup − 15 min` (cook-lead, hardcoded for v1) **or** at
  auto-verify, whichever is later → the promoted ticket joins NOW by
  **age** like everything else (no deadline sort), shows **both**
  clocks (age timer + ready-for), and reads **LATE immediately** if
  overdue — never a fresh 0:00 that hides lateness. Promotion
  highlights the card; the NEW flash is not reused.
- **ready-for** — when an order should be cooked and waiting:
  scheduled = pickup time; ASAP online = verify + 15 min; walk-in =
  POS entry + 15 min (cook lead, hardcoded for v1).
- **Hygiene** — fixed **5 s fetch**, served from the coalesced
  snapshot (§4 *Quota discipline* — ten kiosks cost one RTDB read per
  interval) + **heartbeat**: past ~15 s stale
  the board shows a plain **"Reconnecting…"** banner and **auto-
  reloads** with backoff, so a slept kiosk instance recovers with no
  human and a dead screen never looks live. Tickets clear on **Mark
  served** or, server-owned, at **ready-for + 15 min (or close)** →
  the counter's **ready — awaiting handover** row. **Reject** clears
  the ticket the same instant (the board mirrors *verified,
  unserved*), and **Approve** puts it back **by age** — never a fresh
  0:00, never a reused NEW badge. **Unclaimed** is
  decided at the close sweep, never by the clock.

### Reservations

- Booking fields: type (dine-in / function room / catering), date,
  time, party; calendar/list view; date-time **conflict + duplicate
  check** before confirm (server-run); confirm / cancel statuses.
- **Entry is lookup-first** — staff type the **phone**; a typeahead
  over past reservations prefills name, party, and preferred type
  (`.indexOn phone` on the existing `Reservation` shape — no
  parallel customer table); staff adjust date/time → confirm.
  Returning customer = **≤3 inputs**.
- **New customers** — guided two steps: (1) name · phone · type
  (tap-only chips) ≤3 inputs; (2) date/time pickers + party stepper
  ≤2 → confirm → the conflict check runs itself. Call-script parsing
  and self-serve booking stay out (portal remains rejected).
- **Public availability view** — read-only page on `/`: seats taken
  vs capacity per day/time slot for the week ahead — **confirmed
  bookings only, server-aggregated**, capacities set by admin as
  smart defaults. **Aggregate only**: no names, no phones, no booking
  ability. It answers "is date X free?" without a call and reverses
  nothing.

### Rentals

Crates, plates, furniture — the stock-ruled half of Inventory (§4
*Admin*). Rentals never enter the order stream: the kitchen board
and Top 3 never see them.

- **Catalog** — public read-only on `/`: image, **price per day**,
  **per-date availability** computed as stock vs date-range
  overlaps of confirmed bookings (the reservation conflict math —
  never a mental ledger). Respects the open/closed badge like
  place-order.
- **Booking** — sign in (Google) → **date range + quantity
  steppers** (2 input groups — ≤3 rule) → total computes itself →
  **GCash QR auto-sent** through the same payment gate as orders
  (proof attach, amount/ref flag, 15-min window). Unpaid booking
  lapses — units were never handed over, so stock is untouched.
  **Prepaid in full**; no deposit split in v1.
- **Counter ops** — a **Handover / return** task at the POS:
  **Hand over** on pickup (stock falls), **Confirm return** by the
  due date (stock rises — the flow-level path Add stock never had;
  Add stock stays purchases). Return-due date with a quiet
  **overdue** staff row — no auto-penalty in v1.
- **Admin** — rental items live in Inventory next to Add stock:
  name · image · price/day · units. Bookings get their own
  date-range report view, excluded from Top 3 and the 7-day
  product trend (rankings rank products).

### Admin

- **Dashboard** — KPIs + a **monthly payment mix** (GCash / cash /
  total cards for one business month, stepped with prev/next links —
  an online pickup counts as GCash in full) + 7-day trend (RTDB range
  queries), best-sellers, peak hours, and a **refunds-owed badge**
  (outstanding `refundOwed` count — money owed shouldn't depend on
  someone remembering to open Reports).
- **Products** — CRUD with **image uploads** (server-side resize /
  compress to ~800 px → base64 into RTDB; no file uploads);
  **categories** + per-product
  **add-ons**; **one-click Add promo** (percent or exact price — the
  sibling value and label auto-compute, §3); one-tap **hide** toggle
  — the 86 board (menu items carry no stock, see Inventory).
- **Inventory — rental units only.** Menu items carry no stock: food
  is made to order, and a counter that only ever falls manufactures
  false sold-outs and owner babysitting — sold-out is the **hide**
  toggle instead; the catalog, booking, and handover / return flow
  live in §4 *Rentals*. Stock
  rises via **Add stock** (supplier · qty · date = 3 inputs; qty is
  a stepper, date defaults today) and falls via rental handovers; the
  **low-stock list carries the restock button**. Flags, reports, and
  the inline `− / +` stepper (§3) stay.
- **Staff & settings** — staff accounts (create, demote, delete —
  each evicts that user's cached role, §4 *Auth & roles*); business
  settings: **hours
  per weekday, force-close toggle, scheduled closure ranges,
  announcement banner, GCash number
  + official QR image** (both required before ordering opens, §4
  *Customer site*; shown untouched inside the branded frame at
  checkout — the number alongside for copy/pay-by-number), **Rotate
  kitchen secret**, **reservation capacities** per area (smart
  defaults feeding the public occupancy view), and a **Complete
  setup** banner until the payment block exists.
- **Reports** — sales, payment (GCash vs cash), reservation, and
  rental-booking reports with date filters; a **refunds-owed column** with
  the **Mark refunded**
  action (admin-only — the cashier's money taps are Approve and Mark
  served, nothing else); an **Activity** feed — *time · who · action ·
  order code*, latest N and bounded, built from the audit records and
  the `handledBy` stamps, plus **per-cashier daily totals** (sales
  grouped by `handledBy` — the field rides on the sale, so no extra
  reads); and the
  weekly export-backup runs itself on the Laravel scheduler (same
  mechanism as the sweeps; manual fallback documented) and is
  **incremental by `updatedAt`** (§4 *Quota discipline*, rule 4), so
  the backup never becomes the quota budget's largest reader and
  never skips an order that never settled.

### Design

- §3 applies everywhere: dark mode, motion choreography on the
  public site only, designed empty states, receipts.

### Acceptance

- **Happy path** — browse menu by category → cart → **Sign in with
  Google** → place order → **GCash QR auto-appears** (15-min window
  running) → pay + **Attach screenshot** (newest thumbnail,
  auto-uploads; amount prefilled + ref# typed) → **auto-verified**
  → kitchen
  ticket (order code, running timer; a scheduled pickup sits dimmed
  in LATER, then promotes) → **Mark served** → customer collects with
  the order code → the sale shows in analytics.
- **Negative beats** — a proof-less order **dismisses** at 15:00
  (tracker flips, queue clears); a **failed upload** shows retry and,
  while in flight, **survives** 15:00; a **Restore** re-arms the
  window and flips the tracker back; a **flagged hold → Reject**
  drops the ticket, flips the tracker, and books `refundOwed`, while
  **Approve** returns it by age; **Unclaimed → Collect late** writes
  no second sale; a **duplicate Place order** returns the original
  order code; ordering is **disabled while closed** and **until the
  GCash number + QR are configured**; a conflicting reservation is
  rejected.
- **Throttle** — exceeding the checkout or login limit returns 429
  and recovers inside its window; two customers behind one proxy are
  counted separately (trusted proxies configured).
- **Attribution** — the POS header always names the cashier on duty
  and **Switch account** flips it; a Google account absent from the
  staff list is refused; every sale, walk-in, Mark served, and
  Collect late records `handledBy`, and admin **Activity** reads
  those names back per order code.
- **Rentals** — browse availability → book (date range + qty,
  ≤3 inputs) → QR auto-sent → auto-verified → **Hand over** (stock
  falls) → **Confirm return** (stock rises); an overlapping range
  is blocked; an unpaid booking lapses with stock untouched.
- **Staff paths** — a walk-in ring-up with split tender → receipt; a
  reservation entered → conflict blocked → confirm → shows on the
  calendar; a sale appears in the analytics dashboard.
- **State** — RTDB rules locked down; **all three sweeps on a
  schedule** (expiry `everyMinute`, close at the closing hour, proofs
  daily end-of-day — §4 *Data layer*; manual fallback
  documented); **Firebase quota watched** in the console's Usage tab
  (storage · downloads · connections) — Spark halts the database for
  the rest of the month at the ceiling (§2), so a creeping graph is
  an outage warning, not a bill.
