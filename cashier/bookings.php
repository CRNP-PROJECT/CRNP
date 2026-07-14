<?php
/**
 * cashier/bookings.php — Rental bookings queue.
 * Cashier can approve (from pending), reject (restore stock), or mark
 * returned (restore stock from accepted state). Walk-in bookings created
 * via manual_booking.php also land here.
 */
require_once __DIR__ . '/../init.php';
require_cashier();

$db          = getDB();
$cashierName = $_SESSION['cashier_name'] ?? 'Cashier';

/* ---------- POST: actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action    = (string)post('action', '');
    $bookingId = (string)post('booking_id', '');
    $back      = '/cashier/bookings.php';
    $qs        = trim((string)post('back_query', ''));
    if ($qs !== '') {
        $back .= '?' . $qs;
    }

    if ($bookingId !== '') {
        $booking = $db->retrieve('/bookings/' . $bookingId);
        if (is_array($booking)) {
            $current = (string)($booking['status'] ?? '');
            $short   = substr($bookingId, 0, 6);
            $items   = is_array($booking['items'] ?? null) ? $booking['items'] : [];

            if ($action === 'approve' && $current === 'pending') {
                $db->update('/bookings', $bookingId, [
                    'status'       => 'accepted',
                    'approved_at'  => now(),
                    'approved_by'  => $cashierName,
                ]);
                flash('Booking #' . $short . ' approved.', 'ok');
            } elseif ($action === 'reject' && $current === 'pending') {
                // Restore rent stock by KEY (only on pending → rejected)
                foreach ($items as $itemId => $info) {
                    restore_rent_stock($db, (string)$itemId, (int)($info['qty'] ?? 0));
                }
                $db->update('/bookings', $bookingId, [
                    'status'       => 'rejected',
                    'rejected_at'  => now(),
                    'rejected_by'  => $cashierName,
                ]);
                flash('Booking #' . $short . ' rejected. Rental stock restored.', 'warn');
            } elseif ($action === 'return' && $current === 'accepted') {
                // Restore rent stock by KEY (only on accepted → returned)
                foreach ($items as $itemId => $info) {
                    restore_rent_stock($db, (string)$itemId, (int)($info['qty'] ?? 0));
                }
                $db->update('/bookings', $bookingId, [
                    'status'       => 'returned',
                    'returned_at'  => now(),
                    'returned_by'  => $cashierName,
                ]);
                flash('Booking #' . $short . ' marked returned. Rental stock restored.', 'ok');
            } else {
                flash('That action is not valid for this booking\'s current state.', 'danger');
            }
        } else {
            flash('Booking not found.', 'danger');
        }
    } else {
        flash('No booking selected.', 'danger');
    }
    redirect($back);
}

/* ---------- GET: list ---------- */
$statusFilter = trim((string)($_GET['status'] ?? ''));

$bookings = rows($db->retrieve('/bookings'));
if ($statusFilter !== '') {
    $bookings = filter_by($bookings, 'status', $statusFilter);
}

uasort($bookings, function ($a, $b) {
    $ta = strtotime((string)($a['created_at'] ?? 'now'));
    $tb = strtotime((string)($b['created_at'] ?? 'now'));
    return $tb <=> $ta;
});

$statusOptions = [
    ''          => 'All statuses',
    'pending'   => 'Pending',
    'accepted'  => 'Approved',
    'rejected'  => 'Rejected',
    'returned'  => 'Returned',
    'cancelled' => 'Cancelled',
];

$pageTitle = 'Rental Bookings';
$activeNav = 'bookings';
$layout    = 'wide';
require_once __DIR__ . '/../includes/header.php';

$itemsCount = static function (array $b): int {
    $n = 0;
    foreach (($b['items'] ?? []) as $info) {
        if (is_array($info)) {
            $n += (int)($info['qty'] ?? 0);
        }
    }
    return $n;
};
?>
<header class="page-head">
  <div class="page-head__row">
    <div>
      <span class="eyebrow">Cashier Console</span>
      <h1>Rental bookings</h1>
      <p>Approve rental requests, reject with stock restored, and close out returns when items come back.</p>
    </div>
    <a class="btn btn--gold btn--sm" href="/cashier/manual_booking.php">New walk-in booking</a>
  </div>
</header>

<section class="card">
  <div class="card__head">
    <h2>All bookings</h2>
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
    <?php if (!$bookings): ?>
      <div class="empty" style="border:0;border-radius:0">
        <div class="empty__icon" aria-hidden="true">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
        </div>
        <h3>No bookings found</h3>
        <p><?= $statusFilter !== '' ? 'No bookings match this filter.' : 'There are no rental bookings yet.' ?></p>
      </div>
    <?php else: ?>
      <div class="table-wrap" style="border:0;border-radius:0">
        <table class="tbl">
          <thead>
            <tr>
              <th>Booking</th>
              <th>Customer</th>
              <th>Items</th>
              <th>Total</th>
              <th>Appointment</th>
              <th>Return</th>
              <th>Payment</th>
              <th>Status</th>
              <th class="t-right">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($bookings as $id => $b):
              $st       = (string)($b['status'] ?? '');
              [$sLabel,$sCls] = booking_status_label($st);
              $ps       = (string)($b['payment_status'] ?? '');
              [$pLabel,$pCls] = payment_status_label($ps);
              $custName = (string)($b['user_name'] ?? $b['full_name'] ?? '');
              $contact  = (string)($b['contact'] ?? $b['phone'] ?? '');
              $appt     = (string)($b['appointment_time'] ?? '');
              $ret      = (string)($b['return_time'] ?? '');
              $total    = (float)($b['total'] ?? 0);
              $count    = $itemsCount($b);
              $created  = (string)($b['created_at'] ?? '');
          ?>
            <tr>
              <td>
                <strong>#<?= e(substr((string)$id, 0, 6)) ?></strong>
                <?php if (($b['user_email'] ?? '') === 'walk-in'): ?>
                  <br><span class="badge badge--gold" style="font-size:10px">Walk-in</span>
                <?php endif; ?>
                <?php if ($created !== ''): ?>
                  <br><small class="muted"><?= e($created) ?></small>
                <?php endif; ?>
              </td>
              <td>
                <?= e($custName ?: '—') ?>
                <?php if ($contact !== ''): ?>
                  <br><small class="muted"><?= e($contact) ?></small>
                <?php endif; ?>
              </td>
              <td><?= (int)$count ?> item<?= (int)$count === 1 ? '' : 's' ?></td>
              <td class="num"><strong><?= e(money($total)) ?></strong></td>
              <td><small><?= e($appt ?: '—') ?></small></td>
              <td><small><?= e($ret ?: '—') ?></small></td>
              <td><span class="badge <?= e($pCls) ?>"><?= e($pLabel) ?></span></td>
              <td><span class="badge <?= e($sCls) ?>"><?= e($sLabel) ?></span></td>
              <td class="t-right">
                <div class="row" style="justify-content:flex-end;gap:6px">
                  <?php if ($st === 'pending'): ?>
                    <form method="post" action="/cashier/bookings.php<?= $statusFilter !== '' ? '?status=' . rawurlencode($statusFilter) : '' ?>">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="approve">
                      <input type="hidden" name="booking_id" value="<?= e($id) ?>">
                      <input type="hidden" name="back_query" value="<?= e($statusFilter !== '' ? 'status=' . rawurlencode($statusFilter) : '') ?>">
                      <button class="btn btn--ok btn--sm" type="submit">Approve</button>
                    </form>
                    <form method="post" action="/cashier/bookings.php<?= $statusFilter !== '' ? '?status=' . rawurlencode($statusFilter) : '' ?>" data-confirm="Reject booking #<?= e(substr((string)$id, 0, 6)) ?>? Rental stock will be restored.">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="reject">
                      <input type="hidden" name="booking_id" value="<?= e($id) ?>">
                      <input type="hidden" name="back_query" value="<?= e($statusFilter !== '' ? 'status=' . rawurlencode($statusFilter) : '') ?>">
                      <button class="btn btn--danger btn--sm" type="submit">Reject</button>
                    </form>
                  <?php elseif ($st === 'accepted'): ?>
                    <form method="post" action="/cashier/bookings.php<?= $statusFilter !== '' ? '?status=' . rawurlencode($statusFilter) : '' ?>" data-confirm="Mark booking #<?= e(substr((string)$id, 0, 6)) ?> as returned? Rental stock will be restored.">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="return">
                      <input type="hidden" name="booking_id" value="<?= e($id) ?>">
                      <input type="hidden" name="back_query" value="<?= e($statusFilter !== '' ? 'status=' . rawurlencode($statusFilter) : '') ?>">
                      <button class="btn btn--gold btn--sm" type="submit">Mark returned</button>
                    </form>
                  <?php else: ?>
                    <span class="muted" style="font-size:12px">No actions</span>
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
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
