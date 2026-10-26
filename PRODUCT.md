# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Four roles share one web app, each with its own surface:

- **Customers of the diner** — order online for pickup, pay by GCash, and
  collect with an order code. Browsing is open to everyone; ordering,
  favoriting, and preference sync require Sign in with Google. Not technical;
  they act on a phone.
- **Cashiers (POS staff)** — ring up walk-ins, run the online pickup queue
  (auto-verify, flagged holds, recently-verified spot-check, Dismissed /
  Unclaimed lists), hand over and take back rentals, schedule reservations,
  and print receipts. One shared register, non-technical, touch-first.
- **Kitchen staff** — read-only ticket board (NOW / LATER). No login, no
  interaction: the server owns lane promotion and expiry.
- **Owner / admin** — menu, categories, add-ons, promos, rental-unit
  inventory, staff, settings, reports, dashboard, and the activity feed.

## Product Purpose

One web app that runs **Crates N' Plates Diner** online and in-store:
customers order for pickup and pay by GCash, walk-ins are rung up at the
counter POS, reservations and prepaid rentals are handled at the counter, the
kitchen cooks off a read-only ticket board, and the owner manages menu,
inventory, staff, and reports from one admin surface.

Success is a single system that runs the diner's customer, cashier, kitchen,
and admin workflows end to end — demonstrated live on the internet, not
localhost-only (this is an academic capstone deliverable).

## Positioning

The mechanism a neighbouring product could not truthfully copy is the
combination of **"the system computes, the human only supplies values or
presses buttons"** with **payment as the only gate**:

- Online orders are **pickup-only and GCash-only**; the GCash QR is auto-sent
  at placement (no cashier approval in front of it), the uploaded screenshot
  **auto-verifies** unless the entered amount or GCash ref# mismatches, and the
  happy path costs the cashier **exactly one tap** (Mark served).
- The kitchen board is **read-only** — the server promotes lanes and expires
  tickets; a cook maintains no columns and taps nothing.
- Every derived figure (promo price, "% off", stock totals, booking
  conflicts, change due, open/closed status, Top 3, receipts, report totals)
  is computed, never typed.

## Operating Context

- **One diner, one location.** The system serves Crates N' Plates only — not a
  multi-tenant template.
- **Academic capstone.** The deliverable must run on the internet and be
  demonstrated end to end; the declared stack (Laravel + Firebase RTDB) is part
  of the thesis.
- **Firebase Spark (free) is a hard ceiling.** 1 GB stored · 10 GB/mo
  downloaded (~360 MB/day) · 100 connections; an over-quota month **shuts RTDB
  off** with no throttling. Every read Laravel makes counts, so reads are
  coalesced behind Laravel cache entries and queries are range/key/pagination
  bounded — bandwidth is budgeted as a resource, not treated as free.
- **No build step anywhere.** No npm, no Vite, no Node; Blade templates plus
  one `public/css/app.css`.
- **Shared register, known hands.** Each cashier has their own Google account;
  Switch account is sign-out → sign-in. The kitchen display is a kiosk gated by
  a rotatable shared-secret URL, not a login.
- **All times evaluate in `Asia/Manila`** (the server clock is UTC).
- **No email and no push.** Receipts print at the counter; customers track
  their active order in-session only.
- The **`CONTEXT.md` glossary is authoritative vocabulary** (online order,
  walk-in order, order code, payment window, flagged hold, dismissed, rejected,
  unclaimed, NOW / LATER, handover, return, restock, and the rest); work should
  use those terms, not their avoided synonyms.

## Capabilities and Constraints

**Confirmed capabilities**

- **Customer site (`/`)** — category menu with computed promo strike-through
  and "% off", Top 3 chips, favorites, add-ons with saved per-account
  preferences, cart, 3-tap checkout (pickup time defaulting to ASAP), GCash QR
  in a branded frame, live order tracker (awaiting payment → verifying →
  cooking → READY), proof-of-payment attach with auto-verify, open/closed
  badge, announcement banner, about/branches page, read-only reservation
  occupancy view, and a prepaid rental catalog + booking flow.
- **Cashier POS (`/cashier`)** — walk-in ring-up with an explicit Cash /
  GCash / Cash + GCash payment choice and computed change, online pickup queue with flagged holds and a
  recently-verified spot-check list, Dismissed / Unclaimed lists with Restore /
  Void / Collect late, rental handover / return, staff-entered reservations
  (phone-lookup-first), and receipts.
- **Kitchen (`/kitchen`)** — read-only NOW (oldest-first, age timers, NEW
  badge, all-day counts) and LATER (scheduled pickups, server-promoted at
  pickup − cook lead), with a heartbeat/reconnect banner.
- **Admin (`/admin`)** — dashboard with KPIs, 7-day trend, best-sellers, peak
  hours, a monthly GCash/cash mix, and a refunds-owed badge; product/category/add-on
  CRUD with image uploads; one-click promos; hide toggle; rental-unit
  inventory with Add stock and inline steppers; staff and business settings;
  sales/reservation/rental reports with a refunds-owed column and Mark
  refunded; an Activity feed with per-cashier daily totals.

**Technical constraints**

- **Laravel 12 (PHP 8.2+) + Firebase Realtime Database as the sole datastore.**
  No SQL, no Eloquent; persistence goes through a thin RTDB service.
- **Three trees per order** — `orders/{id}` (status/money/timestamps, never
  image bytes), `proofs/{id}` (the screenshot only), `sales/{id}` (1:1 with the
  order, written at Mark served and synthesized by the close sweep for
  Unclaimed).
- **Server-mediated writes** — customers never write to RTDB directly; order
  POSTs and `users/{uid}` preferences go through Laravel.
- **Four scheduled sweeps** — expiry (`everyMinute`), close (at the weekday
  closing hour / on force-close), proof deletion (daily end-of-day, 7 days
  after terminal or rejected), and the incremental weekly export backup; each
  has a documented manual fallback.
- **Google-only auth (Socialite)** — no passwords anywhere; staff accounts are
  admin-created and an unknown Google account is refused; the first admin comes
  from a one-shot seeder reading environment variables.
- **Rate limits are numbered**, not vibes (checkout 10/min per signed-in user,
  30/min per real IP for anonymous traffic, login 5 per 5 min per IP + email),
  with trusted proxy ranges configured so limits count per customer.

**Undecided / placeholder product facts (do not fabricate)**

- Real menu items, prices, and categories.
- Real branch details beyond the placeholder "Main Branch".
- Real opening hours.
- The real GCash number and official QR image (ordering stays disabled until
  both are configured).

## Brand Commitments

- **Voice: neighbourhood diner.** The `about` page carries it — "A
  neighbourhood diner. Order online for pickup, pay by GCash, collect with your
  order code — no table, no queue at the counter." Copy should stay warm,
  plain, and unfussy.

No other brand commitments (color, palette, or identity rule) were confirmed.

## Evidence on Hand

- **`docs/PLAN.md` / `README.md`** — the living plan and team-facing spec
  (vision, stack, design system, feature specs, acceptance criteria).
- **`CONTEXT.md`** — the authoritative domain glossary.
- **`docs/adr/0001-online-gcash-gated-checkout.md`** — why online orders are
  pickup-only, GCash-only, with payment as the only gate.
- **`docs/acceptance.md`** — the sign-off map from acceptance bullets to tests.
- **`docs/flow-charts/`** — per-role flowcharts (customer, cashier, kitchen,
  admin).
- **`public/images/logo.png`** — an existing logo asset, referenced by page
  headers, the favicon, and receipts.
- **Absent, must not be invented:** real menu/branch/hours content, real GCash
  payment details, customer testimonials, press, benchmarks, or pricing claims.

## Product Principles

1. **The system computes; the human only supplies values or presses buttons.**
   No screen asks a person to do math, write a label, or enter a code.
2. **Payment is the only gate.** No human approval sits in front of the GCash
   QR; verification is default-yes with taps reserved for exceptions.
3. **One tap per online order on the happy path** (Mark served). Extra
   interaction is a defect, not a feature.
4. **The kitchen is read-only.** The server owns lanes, promotion, and expiry;
   a cook maintains no state.
5. **Density is a budget: ≤3 primary actions + 1 labeled overflow per view.**
   Chrome never hides, and destructive actions always confirm.
6. **Bandwidth is a hard resource.** Coalesce polls, cache with write-eviction,
   and keep every query bounded.

## Accessibility & Inclusion

- **Target floors:** interactive targets ≥ 24×24 CSS px (WCAG 2.5.8); cashier
  touch targets ≥ 44 px; content reflows at 320 px; overflow uses "Show more",
  never silent truncation (WCAG 1.4.10).
- **Navigation is always visible and labeled** — never an unlabeled icon.
- **Dark mode is supported** (via `prefers-color-scheme`).
- **Users are non-technical** across every surface; the interface must not
  assume staff or customers can be trained, and empty states are designed, not
  default.
