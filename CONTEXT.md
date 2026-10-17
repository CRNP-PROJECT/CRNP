# Crates N' Plates

One diner system: customers order online for pickup, onsite customers are
rung up at the counter, the kitchen cooks off a read-only board.

## Language

### Orders

**Online order**:
A pickup order placed on the public site. GCash-only, identified by order code.
_Avoid_: delivery order, web order, e-commerce order

**Walk-in order**:
An onsite customer's order entered by the cashier at the POS. May carry a table name; draws an order code at ring-up and settles by cash/GCash split tender.
_Avoid_: counter order, manual order, POS order

**Order code**:
The short code identifying an order at the counter — drawn by online orders at placement and by walk-ins at ring-up (printed on the receipt). Replaces table names for online orders.
_Avoid_: order number, reference number, tracking ID

**Pickup time**:
When a customer asked to collect an online order; ASAP when they didn't.
_Avoid_: scheduled time, delivery slot, ETA

**Order tracker**:
The customer's live screen for their active online order — awaiting payment → verifying → cooking → READY, plus dismissed at the window and payment rejected when a proof is refused. Bound to the session and account; the order code is shown, never typed.
_Avoid_: order status page, order history, tracking ID

### Payment lifecycle

**Payment window**:
The 15 minutes an online order has to be paid, starting when the GCash QR goes out. Ends in dismissal only when no proof was sent and none is mid-upload — an upload started inside the window holds, and a Restore re-arms a fresh one.
_Avoid_: timeout, expiry period, order timer

**Payment verified**:
The order's GCash payment has been accepted — by the system on screenshot upload, unless the entered amount or GCash ref# mismatches, which holds it for the cashier's one-tap Approve/Reject. Only verified orders reach the kitchen.
_Avoid_: payment confirmed, paid status, payment approved

**Flagged hold**:
An order whose entered amount ≠ order total or whose GCash ref# is missing — parked for one Approve/Reject tap. The only thing that interrupts auto-verify; the kitchen waits on it.
_Avoid_: unconfirmed, pending review, 5-min flag

**Recently-verified list**:
Auto-verified orders kept for at-leisure spot-checking — no quota, no timer; Reject stays reachable until Mark served.
_Avoid_: audit queue, sampling queue, review queue

**Dismissed**:
An online order whose payment window expired unpaid. Auto-clears the active queues; retained for Restore (which re-arms a fresh window) or Void.
_Avoid_: cancelled, expired, rejected order

**Rejected**:
An order whose payment proof was refused by the cashier — its own state, not a Dismissed variant. Drops off the kitchen board immediately and books a refund obligation; reachable only by Approve (the undo) or Void, never Restore, and left untouched by the close sweep.
_Avoid_: declined payment, failed payment, disputed order

**Restore**:
Re-activating a dismissed order by re-arming a fresh 15-minute payment window, so the customer re-attaches the same screenshot. Never available on a Rejected order; each one writes an audit entry.
_Avoid_: reopen, reactivate, un-cancel

**Refund owed**:
The obligation booked when a proof is rejected — the customer's entered amount, labelled as claimed rather than verified. Cleared only by the admin's Mark refunded.
_Avoid_: refund issued, chargeback, money back

**Unclaimed**:
A paid, cooked order the customer never collected. Payment is kept — decided at the close sweep, never by the board's timer — and counted as revenue; a later appearance is served with Collect late.
_Avoid_: no-show order, abandoned order, expired order

### Kitchen board

**NOW**:
The kitchen's oldest-first lane of cookable tickets — walk-ins and ASAP pickups.
_Avoid_: active tickets, live queue, main list

**LATER**:
Where scheduled pickups wait as dimmed rows until the server promotes them into NOW at pickup minus the cook lead.
_Avoid_: pending column, upcoming list, backlog

**Cook lead**:
The head start a scheduled pickup gets before its pickup time — the gap that lets the kitchen start it fresh.
_Avoid_: preparation time, lead time buffer, cook window

**Ready-for**:
When an order should be cooked and waiting — pickup time for scheduled pickups, verify + cook lead for ASAP, POS entry + cook lead for walk-ins.
_Avoid_: due time, target time, prep deadline

### Reservations

**Occupancy view**:
The public read-only count of seats taken vs capacity per slot — confirmed bookings only, aggregate, no names. It answers availability questions; it never books.
_Avoid_: availability calendar, booking calendar, live calendar

### Rentals

**Rental booking**:
A customer's reserved rental — date range + quantities, prepaid in full through the GCash gate. Availability computed against stock and overlapping bookings.
_Avoid_: reservation (food), order, equipment hold

**Handover**:
The counter tap that releases a booked rental to the customer — the moment stock falls.
_Avoid_: pickup, release, checkout

**Return**:
The counter tap confirming rented units came back — the moment stock rises. Add stock stays for purchases.
_Avoid_: restock, drop-off, stock-in

**Overdue**:
A rental past its return-due date and not yet returned — a quiet staff row, never an auto-penalty in v1.
_Avoid_: late fee, delinquent, expired rental

### Back office

**Cashier on duty**:
The staff account currently signed into the POS, named in the register header; Switch account is sign-out, then sign in. Each cashier has their own Google account — no passwords.
_Avoid_: current user, active session, operator

**Handled by**:
The name stamped on every cashier-caused write — walk-in ring-up, Mark served, Collect late, and the sale row — that admin's Activity reads back per order code.
_Avoid_: operator ID, processed by, assigned to

**Activity**:
The admin's bounded feed of who did what — time · who · action · order code — built from audit records and Handled-by stamps, plus per-cashier daily totals.
_Avoid_: audit log, action history, event stream

**Restock**:
A receiving entry that raises stock — supplier, quantity, date. The only path that adds rental units.
_Avoid_: purchase order, replenishment, stock-in

**Scheduled closure**:
A date-range closure configured once that flips the site closed by itself; the force-close toggle stays for emergencies.
_Avoid_: holiday mode, blackout, temporary shutdown

### Sales ranking

**Top 3**:
The best-selling products over the rolling 7-day window, ranked on the revenue set (served + Unclaimed) so the chip and the dashboard can never disagree, shown as a chip on product cards.
_Avoid_: best sellers tag, top products badge, trending
