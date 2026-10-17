# Online orders are pickup-only, GCash-only, with payment as the only gate

The original plan had customers ordering from their table with a choice of
Pay at Counter or GCash, identified by table name. We reversed that: **online
orders are pickup orders** (onsite customers are rung up at the POS instead),
identified by **order code**, and **GCash-only** — paying before cooking is
the diner's no-show assurance, so there is no Pay at Counter option online.

Payment is the **only** gate. There is deliberately **no cashier approval
step** before the GCash QR goes out: placing the order sends it immediately
(a human tap between "I ordered" and "I can pay" is pure friction — the cook
review killed it). The customer's GCash screenshot **auto-verifies on
upload**; the cashier taps **Reject** only on exception. This yields exactly
one tap per online order on the happy path: **Mark served** — honoring the
owner's directive that the system be fully system-assisted with minimal
micro-interactions.

## Considered options

- **Cashier approves twice (before QR + after proof)** — rejected: the
  pre-payment tap stalled orders behind cashier availability, and its
  promised user assurance was already voided by the 15-min dismissal
  (accepted-then-dismissed). Payment verification covers both parties.
- **Proof-of-payment review as a required cashier tap** — rejected as the
  default: on-site customer + store-phone GCash notifications make the happy
  path system-served; human review survives only as the Reject exception.
- **Pay at Counter for online orders** — rejected: a paid-in-advance rule
  that doesn't apply to a subset of orders re-opens the no-show problem
  GCash-only solves.

## Consequences

- Unpaid online orders auto-**Dismiss** at 15:00 (visible in-session; no
  external notification channel exists — email/push are rejected in v1).
- Paid-but-never-collected orders become **Unclaimed**: money kept, no
  refund flow (deliberately — a refund stack was never in scope).
- The kitchen board shows only **verified, unserved** tickets; scheduled
  pickups wait dimmed in **LATER** until the server promotes them.
