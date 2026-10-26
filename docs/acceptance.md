# Acceptance evidence — #35

The #35 sign-off maps each PLAN §4 *Acceptance* bullet to the test that proves
it on the shipped system. Every test runs at the highest existing entry point
(HTTP routes, scheduled commands) against the in-memory `RtdbClient` fake, so
the evidence is behavioural: an HTTP response, or state read back through
another public entry point. Run the gate with `vendor/bin/pint --test`,
`vendor/bin/phpstan analyse`, `vendor/bin/pest --ci`.

## Happy path

One test drives browse → cart → Google sign-in → place order → QR + 15-minute
window → Attach + auto-verify → kitchen ticket → Mark served → tracker READY →
sale in analytics:
`tests/Feature/AcceptanceTest.php` — *drives the whole happy path from a
category browse to the sale showing in analytics*.
The beats are also proven in isolation by `CheckoutTest` (browse/cart/place/QR),
`ProofOfPaymentTest` (attach + auto-verify), `KitchenBoardTest` (ticket),
`CashierQueueTest` (Mark served), `AdminDashboardTest` (analytics).

## Negative beats

| Beat | Proving test |
|---|---|
| Proof-less order dismisses at 15:00; tracker flips, queue clears | `AcceptanceTest` — *flips the customer tracker to the dismissed sentence…*; `ScheduledSweepsTest` — *dismisses a zero-proof order past its payment deadline* |
| Failed upload shows retry; in flight survives 15:00 | `ProofOfPaymentTest` — *wires the client to auto-upload…*; `ScheduledSweepsTest` — *holds an order whose proof upload is mid-flight past the deadline* |
| Restore re-arms the window and flips the tracker back | `CashierRetiredOrdersTest` — *restores a dismissed order…* |
| Flagged hold → Reject drops the ticket, flips the tracker, books `refundOwed` | `RejectedOrdersTest` — *writes the rejection record…*, *drops the ticket off the kitchen board…* |
| Approve returns by age (and promotes a flagged hold) | `RejectedOrdersTest` — *rejoins the ticket by age on Approve…*, *approves a flagged hold…* |
| Unclaimed → Collect late writes no second sale | `CashierRetiredOrdersTest` — *collects late an Unclaimed order without writing a second sale* |
| Duplicate Place order returns the original order code | `CheckoutTest` — *returns the original order code on a duplicate…* |
| Ordering disabled while closed / until GCash + QR configured | `CheckoutTest` — *disables ordering while closed…*, *disables ordering until the GCash payment block exists…* |
| Conflicting reservation rejected | `ReservationEntryTest` — *blocks a duplicate reservation with a reason*, *blocks a reservation that would exceed the area capacity…*; `AcceptanceTest` — *enters a reservation, blocks a conflict, confirms…* |

## Throttle

| Beat | Proving test |
|---|---|
| Checkout limit returns 429 | `CheckoutTest` — *throttles place order at 10 per minute per signed-in user* |
| Checkout recovers inside its window | `CheckoutTest` — *recovers the checkout limit inside its one-minute window* |
| Login limit returns 429 | `GoogleSignInTest` — *rate-limits the login routes to five per five minutes per forwarded IP* |
| Login recovers inside its window | `GoogleSignInTest` — *recovers the login limit inside its five-minute window* |
| Two customers behind one proxy counted separately | `CheckoutTest` — *counts anonymous checkout traffic per real forwarded IP*; `GoogleSignInTest` — *counts the login limit per forwarded IP…* |

## Attribution

| Beat | Proving test |
|---|---|
| POS header names the cashier on duty; Switch account flips it | `CashierRegisterHeaderTest` — both cases |
| Google account absent from the staff list refused | `StaffRoleGuardTest` — *refuses a Google account absent from the staff list at /cashier* and *…/admin* |
| Every sale, walk-in, Mark served, Collect late records `handledBy` | `CashierQueueTest` — *marks an order served…*; `CashierWalkInTest` — *stamps handledBy on the walk-in order and its sale row*; `CashierRetiredOrdersTest` — *collects late…* |
| Admin Activity reads those names back per order code | `AdminActivityTest` — *shows the Activity feed with time, who, action, and order code*, *groups per-cashier daily totals by handledBy* |

## Rentals

| Beat | Proving test |
|---|---|
| Browse availability → book → QR → auto-verify → Hand over → Confirm return | `AcceptanceTest` — *drives a rental from a public availability browse through hand over and return*; `RentalsTest` — *lowers stock on hand over and raises it on confirm return* |
| Overlapping range blocked | `RentalsTest` — *blocks a rental range that overlaps a confirmed booking* |
| Unpaid booking lapses with stock untouched | `RentalsTest` — *lapses an unpaid booking with stock untouched* |

## Staff paths

| Beat | Proving test |
|---|---|
| Walk-in ring-up with cash, GCash, or cash + GCash → receipt | `AcceptanceTest` — *rings up a walk-in with cash + GCash and prints the receipt*; `CashierWalkInTest` |
| Reservation entered → conflict blocked → confirm → calendar | `AcceptanceTest` — *enters a reservation, blocks a conflict, confirms…*; `ReservationEntryTest` |
| Sale appears in the analytics dashboard | `AcceptanceTest` — happy path (step 10); `AdminDashboardTest` — *shows the KPIs, the 7-day trend, best-sellers, and peak hours* |
| Monthly GCash vs cash tracked on the dashboard | `AdminDashboardTest` — *shows the monthly payment mix, separating GCash from cash*, *filters the monthly payment mix by the chosen month* |

## State

| Beat | Proving test / command |
|---|---|
| RTDB rules locked down; queried fields indexed | `RtdbDataLayerSmokeTest` — *gates every read and write on an authenticated service account*, *declares an index for every field the data layer queries*; `database.rules.json` root is `auth != null` |
| All four sweeps on a schedule | `php artisan schedule:list` — `orders:expire` (`everyMinute`), `orders:close` (`everyMinute`, gated to the closing hour), `proofs:cleanup` (`59 23 * * *`), `rentals:lapse` (`everyMinute`), `export:run` (weekly) |
| Latest export-backup downloadable by an admin | `AdminDashboardTest` — *downloads the last backup file*, *returns 404 when no backup exists yet* |
| Manual fallback documented | `docs/agents/operations.md`; `ScheduledSweepsTest` — *runs each sweep by hand as the documented manual fallback* |
| Asia/Manila evaluation | `ScheduledSweepsTest` — *runs the three sweeps on the Laravel scheduler in Asia/Manila*; `RentalsTest` — *runs the rental lapse sweep on the Laravel scheduler in Asia/Manila* |
| Firebase quota watched in the console | Operational, not testable in-process — see *Not verified* below |

## Flowcharts

Each of the charts in `docs/flow-charts/` was read against the shipped
code; every documented behaviour is present (routes, views, actions and
services), so no chart is stale. The behaviours are proven by the tests listed
above plus `KitchenBoardTest` (NOW/LATER, promotion, NEW/LATE, all-day, heartbeat),
`OccupancyViewTest` (aggregate read-only), `MenuBrowseTest` (badge, banner,
favorites, Top 3), and `AdminProductsTest` / `AdminSettingsTest` (CRUD, promo,
hide, hours, closures, QR, Rotate secret).

## Not verified in this pass

- **Firebase quota (console Usage tab)** — a manual console observation of
  storage · downloads · connections; it has no in-process test. The read
  discipline that keeps it inside the Spark ceiling is covered by the cache
  tests (`CheckoutTest`, `KitchenBoardTest`, `MenuBrowseTest`, `RentalsTest`).
