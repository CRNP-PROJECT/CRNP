<?php
/**
 * cashier/bookings.php — Rental bookings queue.
 * Cashier can approve (from pending), reject (restore stock), or mark
 * returned (restore stock from accepted state). Walk-in bookings created
 * via manual_booking.php also land here.
 */
require_once __DIR__ . '/../init.php';
require_cashier();

use App\Models\Booking;
use App\Models\RentItem;

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
        $booking = Booking::find($bookingId);
        if ($booking) {
            $current = (string)($booking->status ?? '');
            $short   = substr($bookingId, 0, 6);
            $items   = is_array($booking->get('items') ?? null) ? $booking->get('items') : [];

            if ($action === 'approve' && $current === 'pending') {
                $booking->update([
                    'status'       => 'accepted',
                    'approved_at'  => now(),
                    'approved_by'  => $cashierName,
                ]);
                /* Send booking receipt email. */
                try {
                    $bookingData = $booking->toArray();
                    $bookingData['id'] = $bookingId;
                    $customerEmail = $bookingData['user_email'] ?? '';
                    if ($customerEmail !== '' && $customerEmail !== 'walk-in') {
                        sendBookingReceipt($customerEmail, $bookingData);
                    }
                } catch (Throwable $ex) {}
                flash('Booking #' . $short . ' approved.', 'ok');
            } elseif ($action === 'reject' && $current === 'pending') {
                // Restore rent stock by KEY (only on pending → rejected)
                foreach ($items as $itemId => $info) {
                    RentItem::restoreStock((string)$itemId, (int)($info['qty'] ?? 0));
                }
                $booking->update([
                    'status'       => 'rejected',
                    'rejected_at'  => now(),
                    'rejected_by'  => $cashierName,
                ]);
                flash('Booking #' . $short . ' rejected. Rental stock restored.', 'warn');
            } elseif ($action === 'return' && $current === 'accepted') {
                // Restore rent stock by KEY (only on accepted → returned)
                foreach ($items as $itemId => $info) {
                    RentItem::restoreStock((string)$itemId, (int)($info['qty'] ?? 0));
                }
                $booking->update([
                    'status'       => 'returned',
                    'returned_at'  => now(),
                    'returned_by'  => $cashierName,
                ]);
                flash('Booking #' . $short . ' marked returned. Rental stock restored.', 'ok');
            } elseif ($action === 'mark_paid' && $booking->get('payment_status') !== 'paid') {
                $booking->update([
                    'payment_verified' => true,
                    'payment_status'   => 'paid',
                    'verified_at'      => now(),
                    'verified_by'      => $cashierName,
                ]);
                flash('Booking #' . $short . ' marked as paid.', 'ok');
            } elseif ($action === 'mark_unpaid' && $booking->get('payment_status') === 'paid') {
                $booking->update([
                    'payment_verified' => false,
                    'payment_status'   => 'unpaid',
                ]);
                flash('Booking #' . $short . ' reverted to unpaid.', 'warn');
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
$bookings = Booking::raw();

// Split into active (pending) and history (accepted/returned/rejected/cancelled)
$activeBookings = [];
$historyBookings = [];
foreach ($bookings as $bid => $b) {
    if (!is_array($b)) continue;
    $st = (string)($b['status'] ?? '');
    if ($st === 'pending') {
        $activeBookings[$bid] = $b;
    } else {
        $historyBookings[$bid] = $b;
    }
}

// Newest first
uasort($activeBookings, function ($a, $b) {
    $ta = strtotime((string)($a['created_at'] ?? 'now'));
    $tb = strtotime((string)($b['created_at'] ?? 'now'));
    return $tb <=> $ta;
});
uasort($historyBookings, function ($a, $b) {
    $ta = strtotime((string)($a['created_at'] ?? 'now'));
    $tb = strtotime((string)($b['created_at'] ?? 'now'));
    return $tb <=> $ta;
});

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
      <p>Approve pending requests and manage completed bookings.</p>
    </div>
    <a class="btn btn--gold btn--sm" href="/cashier/manual_booking.php">New walk-in booking</a>
  </div>
</header>

<!-- ============ PENDING REQUESTS ============ -->
<section class="card">
  <div class="card__head">
    <h2>Pending requests (<?= count($activeBookings) ?>)</h2>
  </div>
  <div class="card__body" style="padding:0">
    <?php if (!$activeBookings): ?>
      <div class="empty" style="border:0;border-radius:0">
        <div class="empty__icon" aria-hidden="true">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
        </div>
        <h3>No pending requests</h3>
        <p>All rental requests have been processed.</p>
      </div>
    <?php else: ?>
      <div class="table-wrap" style="border:0;border-radius:0">
        <table class="tbl">
          <thead>
            <tr>
              <th>Booking</th>
              <th>Customer</th>
              <th>Items</th>
              <th class="num">Total</th>
              <th>Appointment</th>
              <th>Return</th>
              <th>Payment</th>
              <th>Placed</th>
              <th class="t-right">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($activeBookings as $id => $b):
              $st       = (string)($b['status'] ?? '');
              [$sLabel,$sCls] = booking_status_label($st);
              $pm       = (string)($b['payment_method'] ?? '');
              $ps       = (string)($b['payment_status'] ?? '');
              [$pLabel,$pCls] = payment_status_label($ps);
              $custName = (string)($b['customer_name'] ?? $b['full_name'] ?? $b['user_name'] ?? '');
              $contact  = (string)($b['contact'] ?? $b['phone'] ?? '');
              $total    = (float)($b['total'] ?? 0);
              $appt     = (string)($b['appointment_time'] ?? '');
              $ret      = (string)($b['return_time'] ?? '');
              $created  = (string)($b['created_at'] ?? '');
              $receipt  = (string)($b['receipt'] ?? '');
              $isGcash  = $pm === 'gcash';
          ?>
            <tr>
              <td><strong>#<?= e(substr((string)$id, 0, 6)) ?></strong>
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
              <td><?= items_html($b['items'] ?? []) ?></td>
              <td class="num"><strong><?= e(money($total)) ?></strong></td>
              <td><small><?= e($appt ?: '—') ?></small></td>
              <td><small><?= e($ret ?: '—') ?></small></td>
              <td>
                <span class="badge <?= e($pCls) ?>"><?= e($pLabel) ?></span>
                <?php if ($isGcash): ?>
                  <div class="mt-2">
                    <?php if ($receipt !== ''): ?>
                      <button class="btn btn--ghost btn--sm" type="button" data-receipt="<?= e(image_display_src($receipt, 'user/bookings')) ?>">View receipt</button>
                    <?php else: ?>
                      <span class="muted" style="font-size:12px">No receipt</span>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              </td>
              <td><small class="muted"><?= e($created ?: '—') ?></small></td>
              <td class="t-right">
                <div class="row" style="justify-content:flex-end;gap:6px">
                  <form method="post" action="/cashier/bookings.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="booking_id" value="<?= e($id) ?>">
                    <input type="hidden" name="back_query" value="">
                    <button class="btn btn--ok btn--sm" type="submit">Approve</button>
                  </form>
                  <form method="post" action="/cashier/bookings.php" data-confirm="Reject booking #<?= e(substr((string)$id, 0, 6)) ?>? Rental stock will be restored.">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="booking_id" value="<?= e($id) ?>">
                    <input type="hidden" name="back_query" value="">
                    <button class="btn btn--danger btn--sm" type="submit">Reject</button>
                  </form>
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

<!-- ============ HISTORY ============ -->
<section class="card" style="margin-top:24px">
  <div class="card__head">
    <h2>History (<?= count($historyBookings) ?>)</h2>
  </div>
  <div class="card__body" style="padding:0">
    <?php if (!$historyBookings): ?>
      <div class="empty" style="border:0;border-radius:0">
        <div class="empty__icon" aria-hidden="true">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
        </div>
        <h3>No history yet</h3>
        <p>Approved bookings that are waiting to be returned will appear here.</p>
      </div>
    <?php else: ?>
      <div class="table-wrap" style="border:0;border-radius:0">
        <table class="tbl">
          <thead>
            <tr>
              <th>Booking</th>
              <th>Customer</th>
              <th>Items</th>
              <th class="num">Total</th>
              <th>Appointment</th>
              <th>Return</th>
              <th>Payment</th>
              <th>Status</th>
              <th class="t-right">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($historyBookings as $id => $b):
              $st       = (string)($b['status'] ?? '');
              [$sLabel,$sCls] = booking_status_label($st);
              $pm       = (string)($b['payment_method'] ?? '');
              $ps       = (string)($b['payment_status'] ?? '');
              [$pLabel,$pCls] = payment_status_label($ps);
              $custName = (string)($b['customer_name'] ?? $b['full_name'] ?? $b['user_name'] ?? '');
              $contact  = (string)($b['contact'] ?? $b['phone'] ?? '');
              $total    = (float)($b['total'] ?? 0);
              $appt     = (string)($b['appointment_time'] ?? '');
              $ret      = (string)($b['return_time'] ?? '');
              $created  = (string)($b['created_at'] ?? '');
              $receipt  = (string)($b['receipt'] ?? '');
              $isGcash  = $pm === 'gcash';
              $canPrint = in_array($st, ['accepted', 'returned'], true);
          ?>
            <tr>
              <td><strong>#<?= e(substr((string)$id, 0, 6)) ?></strong>
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
              <td><?= items_html($b['items'] ?? []) ?></td>
              <td class="num"><strong><?= e(money($total)) ?></strong></td>
              <td><small><?= e($appt ?: '—') ?></small></td>
              <td><small><?= e($ret ?: '—') ?></small></td>
              <td>
                <span class="badge <?= e($pCls) ?>"><?= e($pLabel) ?></span>
                <?php if ($isGcash && $receipt !== ''): ?>
                  <div class="mt-2">
                    <button class="btn btn--ghost btn--sm" type="button" data-receipt="<?= e(image_display_src($receipt, 'user/bookings')) ?>">View receipt</button>
                  </div>
                <?php endif; ?>
              </td>
              <td><span class="badge <?= e($sCls) ?>"><?= e($sLabel) ?></span></td>
              <td class="t-right">
                <div class="row" style="justify-content:flex-end;gap:6px">
                  <?php if ($st === 'accepted'): ?>
                    <form method="post" action="/cashier/bookings.php" data-confirm="Mark booking #<?= e(substr((string)$id, 0, 6)) ?> as returned? Rental stock will be restored.">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="return">
                      <input type="hidden" name="booking_id" value="<?= e($id) ?>">
                      <input type="hidden" name="back_query" value="">
                      <button class="btn btn--gold btn--sm" type="submit">Mark returned</button>
                    </form>
                  <?php endif; ?>
                  <?php if ($st !== 'rejected' && $st !== 'cancelled'): ?>
                    <?php if ($ps !== 'paid'): ?>
                      <form method="post" action="/cashier/bookings.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="mark_paid">
                        <input type="hidden" name="booking_id" value="<?= e($id) ?>">
                        <input type="hidden" name="back_query" value="">
                        <button class="btn btn--gold btn--sm" type="submit">Mark paid</button>
                      </form>
                    <?php else: ?>
                      <form method="post" action="/cashier/bookings.php" data-confirm="Revert this payment to unpaid?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="mark_unpaid">
                        <input type="hidden" name="booking_id" value="<?= e($id) ?>">
                        <input type="hidden" name="back_query" value="">
                        <button class="btn btn--outline btn--sm" type="submit">Mark unpaid</button>
                      </form>
                    <?php endif; ?>
                  <?php endif; ?>
                  <?php if ($canPrint): ?>
                    <a class="btn btn--ghost btn--sm" href="/cashier/booking_receipt.php?id=<?= e($id) ?>">Print receipt</a>
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
  .receipt-modal { position:fixed; inset:0; z-index:10000; display:flex; align-items:center; justify-content:center; background:rgba(0,0,0,.7); }
  .receipt-modal[hidden] { display:none; }
  .receipt-modal__inner { position:relative; max-width:90vw; max-height:90vh; }
  .receipt-modal__img { display:block; max-width:90vw; max-height:90vh; border-radius:8px; box-shadow:0 8px 40px rgba(0,0,0,.5); }
  .receipt-modal__close { position:absolute; top:-36px; right:0; background:rgba(0,0,0,.5); color:#fff; border:0; border-radius:6px; width:32px; height:32px; font-size:20px; cursor:pointer; display:flex; align-items:center; justify-content:center; }
  .receipt-modal__close:hover { background:rgba(0,0,0,.7); }
</style>

<script>
(function() {
  var modal = document.getElementById('receiptModal');
  if (!modal) return;
  var modalImg = document.getElementById('receiptModalImg');
  var closeBtn = modal.querySelector('.receipt-modal__close');
  function open(src) { modalImg.src = src; modal.removeAttribute('hidden'); }
  function close() { modal.setAttribute('hidden', ''); modalImg.src = ''; }
  closeBtn.addEventListener('click', close);
  modal.addEventListener('click', function(e) { if (e.target === modal) close(); });
  document.addEventListener('keydown', function(e) { if (e.key === 'Escape') close(); });
  document.querySelectorAll('[data-receipt]').forEach(function(btn) {
    btn.addEventListener('click', function() { open(btn.getAttribute('data-receipt')); });
  });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
