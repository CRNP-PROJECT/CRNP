<?php
/**
 * cashier/index.php — Orders management console.
 * Lists all customer orders with stats, a status filter, and per-row
 * action buttons: Accept, Cancel (cashier), Restore, Mark paid/unpaid (GCash only).
 *
 * P0/P1 improvements (Task A):
 *  1. Real-time polling — ?check=1 JSON endpoint + 20s JS poll + non-intrusive
 *     toast (no auto-reload) + green "Live" pulsing indicator in the header.
 *  2. Cancel undo / Restore — cashier_cancelled -> pending (no stock restore).
 *  3. Receipt lightbox — inline modal replaces the new-tab <a> link.
 *  4. Bulk "Accept all pending" — one POST accepts every pending order.
 */
require_once __DIR__ . '/../init.php';
require_cashier();

$db          = getDB();
$cashierName = $_SESSION['cashier_name'] ?? 'Cashier';

/* ---------- POST: per-row + bulk actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action  = (string)post('action', '');
    $orderId = (string)post('order_id', '');
    $back    = '/cashier/';
    $qs      = trim((string)post('back_query', ''));
    if ($qs !== '') {
        $back .= '?' . $qs;
    }

    /* --- Bulk: accept every pending order (no order_id required) --- */
    if ($action === 'accept_all_pending') {
        $all = rows($db->retrieve('/orders'));
        $now = now();
        $n   = 0;
        foreach ($all as $oid => $o) {
            if (!is_array($o)) {
                continue;
            }
            if ((string)($o['status'] ?? '') === 'pending') {
                $db->update('/orders', $oid, [
                    'status'      => 'accepted',
                    'accepted_at' => $now,
                    'accepted_by' => $cashierName,
                ]);
                $n++;
            }
        }
        if ($n > 0) {
            flash('Accepted ' . $n . ' pending order' . ($n === 1 ? '' : 's') . '.', 'ok');
        } else {
            flash('No pending orders to accept.', 'info');
        }
        redirect($back);
    }

    /* --- Per-order actions --- */
    if ($orderId !== '') {
        $order = $db->retrieve('/orders/' . $orderId);
        if (is_array($order)) {
            $current = (string)($order['status'] ?? '');
            $short   = substr($orderId, 0, 6);

            if ($action === 'accept' && $current === 'pending') {
                $db->update('/orders', $orderId, [
                    'status'      => 'accepted',
                    'accepted_at' => now(),
                    'accepted_by' => $cashierName,
                ]);
                flash('Order #' . $short . ' accepted.', 'ok');
            } elseif ($action === 'cancel') {
                $note = trim((string)post('cancel_note', ''));
                $db->update('/orders', $orderId, [
                    'status'        => 'cashier_cancelled',
                    'cancelled_by'  => $cashierName,
                    'cancel_note'   => $note,
                    'cancelled_at'  => now(),
                ]);
                flash('Order #' . $short . ' cancelled.', 'warn');
            } elseif ($action === 'restore' && $current === 'cashier_cancelled') {
                // Restore a cashier-cancelled order back to pending.
                // NOTE: product stock is intentionally NOT touched — the
                // cashier cancel flow never deducted stock (only bookings do
                // that), so there is nothing to refund here. The status guard
                // above prevents double-restore race conditions.
                $db->update('/orders', $orderId, [
                    'status'      => 'pending',
                    'restored_by' => $cashierName,
                    'restored_at' => now(),
                ]);
                flash('Order #' . $short . ' restored to pending.', 'ok');
            } elseif ($action === 'mark_paid' && ($order['payment_status'] ?? '') !== 'paid') {
                $db->update('/orders', $orderId, [
                    'payment_verified' => true,
                    'payment_status'   => 'paid',
                    'verified_at'      => now(),
                    'verified_by'      => $cashierName,
                ]);
                flash('Order #' . $short . ' marked as paid.', 'ok');
                $email = $order['user_email'] ?? '';
                if ($email !== '' && $email !== 'walk-in') {
                    $order['id'] = $orderId;
                    $order['full_name'] = $order['full_name'] ?? $order['customer_name'] ?? '';
                    sendOrderReceipt($email, $order);
                }
            } elseif ($action === 'mark_unpaid' && ($order['payment_status'] ?? '') === 'paid') {
                $db->update('/orders', $orderId, [
                    'payment_verified' => false,
                    'payment_status'   => 'unpaid',
                ]);
                flash('Order #' . $short . ' reverted to unpaid.', 'warn');
            } else {
                flash('That action is not valid for this order\'s current state.', 'danger');
            }
        } else {
            flash('Order not found.', 'danger');
        }
    } else {
        flash('No order selected.', 'danger');
    }
    redirect($back);
}

/* ---------- GET: list + stats ---------- */
$statusFilter = trim((string)($_GET['status'] ?? ''));

$allOrders = rows($db->retrieve('/orders'));

// Stats (always over the full set)
$pendingCount = 0;
$unpaidCount  = 0;
$todaySales   = 0.0;
$today        = date('Y-m-d');
foreach ($allOrders as $o) {
    if (!is_array($o)) {
        continue;
    }
    $st = (string)($o['status'] ?? '');
    if ($st === 'pending') {
        $pendingCount++;
    }
    if (($o['payment_status'] ?? '') !== 'paid'
        && $st !== 'cashier_cancelled' && $st !== 'cancelled') {
        $unpaidCount++;
    }
    $date = substr((string)($o['created_at'] ?? $o['placed_at'] ?? ''), 0, 10);
    if ($date === $today
        && ($o['payment_status'] ?? '') === 'paid'
        && $st !== 'cashier_cancelled' && $st !== 'cancelled') {
        $todaySales += (float)($o['total'] ?? 0);
    }
}

/* Lightweight polling endpoint consumed by the inline JS below.
   Returns just the current pending count as JSON so the page can poll
   cheaply every 20s without re-fetching the whole table. */
if (isset($_GET['check'])) {
    header('Content-Type: application/json');
    echo json_encode(['pending' => (int)$pendingCount]);
    exit;
}

// Apply filter for display
$orders = $allOrders;
if ($statusFilter !== '') {
    $orders = filter_by($orders, 'status', $statusFilter);
}

// Newest first
uasort($orders, function ($a, $b) {
    $ta = strtotime((string)($a['created_at'] ?? $a['placed_at'] ?? 'now'));
    $tb = strtotime((string)($b['created_at'] ?? $b['placed_at'] ?? 'now'));
    return $tb <=> $ta;
});

$statusOptions = [
    ''                   => 'All statuses',
    'pending'            => 'Pending',
    'accepted'           => 'Accepted',
    'preparing'          => 'Preparing',
    'ready'              => 'Ready',
    'done'               => 'Completed',
    'cashier_cancelled'  => 'Cancelled (cashier)',
];

$pageTitle = 'Orders';
$activeNav = 'orders';
$layout    = 'wide';
require_once __DIR__ . '/../includes/header.php';

/** helper: total qty in an order's items map */
$itemsCount = static function (array $order): int {
    $n = 0;
    foreach (($order['items'] ?? []) as $info) {
        if (is_array($info)) {
            $n += (int)($info['qty'] ?? 0);
        }
    }
    return $n;
};

// Shared back-URL helpers (filter-aware) — used by every action form.
$backQuery  = $statusFilter !== '' ? 'status=' . rawurlencode($statusFilter) : '';
$backAction = '/cashier/' . ($statusFilter !== '' ? '?status=' . rawurlencode($statusFilter) : '');
?>
<header class="page-head">
  <div class="page-head__row">
    <div>
      <span class="eyebrow">Cashier Console</span>
      <h1 style="display:inline-flex;align-items:center;gap:12px;flex-wrap:wrap">
        Orders
        <span class="live-indicator" id="liveIndicator" title="Live polling active — checks for new orders every 20 seconds">
          <span class="live-dot" aria-hidden="true"></span>
          <span class="live-text">Live</span>
        </span>
      </h1>
      <p>Accept incoming orders, verify GCash payments, and flag cancellations for the kitchen.</p>
    </div>
    <div class="row">
      <form method="post" action="<?= e($backAction) ?>" data-confirm="Accept ALL pending orders at once?">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="accept_all_pending">
        <input type="hidden" name="back_query" value="<?= e($backQuery) ?>">
        <button class="btn btn--ok btn--sm" type="submit">Accept all pending</button>
      </form>
      <a class="btn btn--outline btn--sm" href="/cashier/bookings.php">View bookings</a>
      <a class="btn btn--gold btn--sm" href="/cashier/manual_booking.php">New walk-in booking</a>
    </div>
  </div>
</header>

<section class="grid grid--stat mb-4" aria-label="Order summary" style="margin-top:40px;">
  <div class="stat">
    <div class="stat__label">Pending orders</div>
    <div class="stat__value"><?= (int)$pendingCount ?></div>
    <div class="stat__delta">Awaiting acceptance</div>
  </div>
  <div class="stat">
    <div class="stat__label">Unpaid orders</div>
    <div class="stat__value"><?= (int)$unpaidCount ?></div>
    <div class="stat__delta">Awaiting payment confirmation</div>
  </div>
  <div class="stat">
    <div class="stat__label">Today&rsquo;s sales</div>
    <div class="stat__value"><?= e(money($todaySales)) ?></div>
    <div class="stat__delta">From paid orders, <?= e(date('M j, Y')) ?></div>
  </div>
</section>

<section class="card">
  <div class="card__head">
    <h2>All orders</h2>
    <form method="get" class="row" style="gap:8px">
      <label for="status" class="sr-only">Filter by status</label>
      <select class="select" id="status" name="status" onchange="this.form.submit()">
        <?php foreach ($statusOptions as $val => $label): ?>
          <option value="<?= e($val) ?>" <?= $val === $statusFilter ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <noscript><button class="btn btn--outline btn--sm" type="submit">Filter</button></noscript>
    </form>
  </div>

  <div class="card__body" style="padding:0">
    <?php if (!$orders): ?>
      <div class="empty" style="border:0;border-radius:0">
        <div class="empty__icon" aria-hidden="true">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
        </div>
        <h3>No orders found</h3>
        <p><?= $statusFilter !== '' ? 'No orders match this filter.' : 'There are no customer orders yet.' ?></p>
      </div>
    <?php else: ?>
      <div class="table-wrap" style="border:0;border-radius:0">
        <table class="tbl">
          <thead>
            <tr>
              <th>Order</th>
              <th>Customer</th>
              <th>Items</th>
              <th>Total</th>
              <th>Payment</th>
              <th>Status</th>
              <th>Placed</th>
              <th class="t-right">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($orders as $id => $o):
              $st       = (string)($o['status'] ?? '');
              [$sLabel,$sCls] = order_status_label($st);
              $pm       = (string)($o['payment_method'] ?? '');
              $ps       = (string)($o['payment_status'] ?? '');
              [$pLabel,$pCls] = payment_status_label($ps);
              $custName = (string)($o['customer_name'] ?? $o['user_name'] ?? '');
              $contact  = (string)($o['contact'] ?? $o['phone'] ?? $o['customer_contact'] ?? '');
              $placed   = (string)($o['created_at'] ?? $o['placed_at'] ?? '');
              $total    = (float)($o['total'] ?? 0);
              $count    = $itemsCount($o);
              $receipt  = (string)($o['receipt'] ?? $o['gcash_receipt'] ?? '');
              $isGcash  = $pm === 'gcash';
              $isPaid   = $ps === 'paid';
          ?>
            <tr>
              <td><strong>#<?= e(substr((string)$id, 0, 6)) ?></strong></td>
              <td>
                <?= e($custName ?: '—') ?>
                <?php if ($contact !== ''): ?>
                  <br><small class="muted"><?= e($contact) ?></small>
                <?php endif; ?>
              </td>
              <td><?= (int)$count ?> item<?= (int)$count === 1 ? '' : 's' ?></td>
              <td class="num"><strong><?= e(money($total)) ?></strong></td>
              <td>
                <span class="badge <?= e($pCls) ?>"><?= e($pLabel) ?></span>
                <?php if ($isGcash): ?>
                  <div class="mt-2">
                    <?php if ($receipt !== ''): ?>
                      <button class="btn btn--ghost btn--sm" type="button" data-receipt="<?= e(upload_web('user/bookings', $receipt)) ?>">View receipt</button>
                    <?php else: ?>
                      <span class="muted" style="font-size:12px">No receipt uploaded</span>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              </td>
              <td><span class="badge <?= e($sCls) ?>"><?= e($sLabel) ?></span></td>
              <td><small class="muted"><?= e($placed ?: '—') ?></small></td>
              <td class="t-right">
                <div class="row" style="justify-content:flex-end;gap:6px">
                  <?php if ($st === 'pending'): ?>
                    <form method="post" action="<?= e($backAction) ?>">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="accept">
                      <input type="hidden" name="order_id" value="<?= e($id) ?>">
                      <input type="hidden" name="back_query" value="<?= e($backQuery) ?>">
                      <button class="btn btn--ok btn--sm" type="submit">Accept</button>
                    </form>
                  <?php endif; ?>

                  <?php if ($st !== 'cashier_cancelled' && $st !== 'cancelled' && $st !== 'done'): ?>
                    <form method="post" action="<?= e($backAction) ?>" data-confirm="Cancel order #<?= e(substr((string)$id, 0, 6)) ?>? The kitchen will be notified.">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="cancel">
                      <input type="hidden" name="order_id" value="<?= e($id) ?>">
                      <input type="hidden" name="back_query" value="<?= e($backQuery) ?>">
                      <input type="hidden" name="cancel_note" id="cancelNote" value="">
                      <button class="btn btn--danger btn--sm" type="submit">Cancel</button>
                    </form>
                  <?php endif; ?>

                  <?php if ($st === 'cashier_cancelled'): ?>
                    <form method="post" action="<?= e($backAction) ?>" data-confirm="Restore order #<?= e(substr((string)$id, 0, 6)) ?> back to pending?">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="restore">
                      <input type="hidden" name="order_id" value="<?= e($id) ?>">
                      <input type="hidden" name="back_query" value="<?= e($backQuery) ?>">
                      <button class="btn btn--ghost btn--sm" type="submit">Restore</button>
                    </form>
                  <?php endif; ?>

                  <?php if ($st !== 'cashier_cancelled' && $st !== 'cancelled' && $st !== 'done'): ?>
                    <?php if (!$isPaid): ?>
                      <form method="post" action="<?= e($backAction) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="mark_paid">
                        <input type="hidden" name="order_id" value="<?= e($id) ?>">
                        <input type="hidden" name="back_query" value="<?= e($backQuery) ?>">
                        <button class="btn btn--gold btn--sm" type="submit">Mark paid</button>
                      </form>
                    <?php else: ?>
                      <form method="post" action="<?= e($backAction) ?>" data-confirm="Revert this payment to unpaid?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="mark_unpaid">
                        <input type="hidden" name="order_id" value="<?= e($id) ?>">
                        <input type="hidden" name="back_query" value="<?= e($backQuery) ?>">
                        <button class="btn btn--outline btn--sm" type="submit">Mark unpaid</button>
                      </form>
                    <?php endif; ?>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- Receipt lightbox modal -->
<div id="receiptModal" class="receipt-modal" hidden>
  <div class="receipt-modal__inner">
    <button class="receipt-modal__close" type="button" aria-label="Close receipt preview">&times;</button>
    <img id="receiptModalImg" class="receipt-modal__img" alt="GCash receipt preview">
  </div>
</div>

<style>
  /* ---- Live polling indicator (green pulsing dot) ---- */
  .live-indicator {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #16a34a;
    padding: 4px 10px;
    border-radius: 999px;
    background: rgba(34, 197, 94, 0.12);
    border: 1px solid rgba(34, 197, 94, 0.35);
    vertical-align: middle;
  }
  .live-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #22c55e;
    animation: live-pulse 1.6s ease-in-out infinite;
  }
  @keyframes live-pulse {
    0%   { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); }
    70%  { box-shadow: 0 0 0 7px rgba(34, 197, 94, 0); }
    100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
  }

  /* ---- New-orders toast (non-intrusive, bottom-right) ---- */
  .poll-toast {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 10000;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 14px;
    border-radius: 12px;
    background: var(--surface, #fff);
    color: var(--ink, #211b14);
    border: 1px solid var(--line, #e6dfd1);
    box-shadow: 0 18px 40px rgba(0, 0, 0, 0.25);
    font-size: 14px;
    max-width: 380px;
    animation: poll-slide-in 0.28s ease;
  }
  .poll-toast__msg { flex: 1; }
  @keyframes poll-slide-in {
    from { transform: translateY(16px); opacity: 0; }
    to   { transform: translateY(0); opacity: 1; }
  }

  /* ---- Receipt lightbox ---- */
  .receipt-modal {
    position: fixed;
    inset: 0;
    z-index: 9999;
    background: rgba(8, 6, 4, 0.86);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 28px;
  }
  .receipt-modal[hidden] { display: none; }
  .receipt-modal__inner {
    position: relative;
    max-width: 90vw;
    max-height: 85vh;
  }
  .receipt-modal__img {
    display: block;
    max-width: 90vw;
    max-height: 85vh;
    border-radius: 8px;
    background: #fff;
    box-shadow: 0 24px 70px rgba(0, 0, 0, 0.55);
  }
  .receipt-modal__close {
    position: absolute;
    top: -16px;
    right: -16px;
    width: 38px;
    height: 38px;
    border-radius: 50%;
    border: 0;
    background: #fff;
    color: #111;
    font-size: 24px;
    line-height: 1;
    cursor: pointer;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.4);
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .receipt-modal__close:hover { background: #f3f3f3; }

  @media (max-width: 600px) {
    .poll-toast { left: 12px; right: 12px; bottom: 12px; max-width: none; }
    .receipt-modal__close { top: 6px; right: 6px; }
  }
</style>

<script>
  /* ============================================================
     1) Real-time polling — fetch /cashier/?check=1 every 20s.
        If the pending count rose, show a non-intrusive toast with
        a Refresh button. The page is NEVER auto-reloaded (that
        would interrupt the cashier mid-action).
     ============================================================ */
  (function () {
    var lastPending = <?= (int)$pendingCount ?>;
    var pollUrl     = window.location.pathname + '?check=1';

    function showToast(message) {
      var old = document.getElementById('pollToast');
      if (old) { old.remove(); }

      var t = document.createElement('div');
      t.id = 'pollToast';
      t.className = 'poll-toast';

      var msg = document.createElement('span');
      msg.className = 'poll-toast__msg';
      msg.textContent = message;

      var refresh = document.createElement('button');
      refresh.className = 'btn btn--gold btn--sm';
      refresh.type = 'button';
      refresh.textContent = 'Refresh';
      refresh.addEventListener('click', function () { window.location.reload(); });

      var dismiss = document.createElement('button');
      dismiss.className = 'btn btn--ghost btn--sm';
      dismiss.type = 'button';
      dismiss.setAttribute('aria-label', 'Dismiss notification');
      dismiss.textContent = '\u00D7';
      dismiss.addEventListener('click', function () { t.remove(); });

      t.appendChild(msg);
      t.appendChild(refresh);
      t.appendChild(dismiss);
      document.body.appendChild(t);
    }

    setInterval(function () {
      fetch(pollUrl, { cache: 'no-store', headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
        .then(function (d) {
          var n = parseInt(d.pending, 10) || 0;
          if (n > lastPending) {
            showToast('New orders received \u2014 refresh to view.');
          }
          lastPending = n;
        })
        .catch(function () { /* network hiccup — stay silent */ });
    }, 20000);
  })();

  /* ============================================================
     2) Receipt lightbox — opens [data-receipt] image in an
        inline modal. Closes on: close button, backdrop click, Esc.
     ============================================================ */
  (function () {
    var modal    = document.getElementById('receiptModal');
    var modalImg = document.getElementById('receiptModalImg');
    if (!modal || !modalImg) { return; }
    var closeBtn = modal.querySelector('.receipt-modal__close');

    function open(src) {
      modalImg.src = src;
      modal.removeAttribute('hidden');
      document.body.style.overflow = 'hidden';
    }
    function close() {
      modal.setAttribute('hidden', '');
      modalImg.src = '';
      document.body.style.overflow = '';
    }

    document.querySelectorAll('[data-receipt]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var src = btn.getAttribute('data-receipt');
        if (src) { open(src); }
      });
    });

    if (closeBtn) { closeBtn.addEventListener('click', close); }
    modal.addEventListener('click', function (e) {
      if (e.target === modal) { close(); }
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !modal.hasAttribute('hidden')) { close(); }
    });
  })();
  /* Cancel reason prompt — hides inline input, shows prompt() on submit */
  document.querySelectorAll('form input#cancelNote').forEach(function(inp) {
    var form = inp.closest('form');
    if (!form) return;
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      var reason = prompt('Reason for cancellation (optional):');
      if (reason === null) return;
      inp.value = reason || '';
      HTMLFormElement.prototype.submit.call(form);
    });
  });
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
