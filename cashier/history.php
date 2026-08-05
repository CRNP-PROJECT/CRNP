<?php
/**
 * cashier/history.php — read-only archive of cancelled/completed orders
 * and rejected/returned/cancelled bookings.
 */
require_once __DIR__ . '/../init.php';
require_cashier();

use App\Models\Order;
use App\Models\Booking;

$orderStatuses   = ['cashier_cancelled', 'cancelled', 'done'];
$bookingStatuses = ['rejected', 'returned', 'cancelled'];

$orders   = Order::raw();
$bookings = Booking::raw();

// Filter
$ordersHist = [];
foreach ($orders as $id => $o) {
    if (is_array($o) && in_array((string)($o['status'] ?? ''), $orderStatuses, true)) {
        $ordersHist[$id] = $o;
    }
}
$bookingsHist = [];
foreach ($bookings as $id => $b) {
    if (is_array($b) && in_array((string)($b['status'] ?? ''), $bookingStatuses, true)) {
        $bookingsHist[$id] = $b;
    }
}

// Sort newest first
uasort($ordersHist, function ($a, $b) {
    $ta = strtotime((string)($a['cancelled_at'] ?? $a['created_at'] ?? 'now'));
    $tb = strtotime((string)($b['cancelled_at'] ?? $b['created_at'] ?? 'now'));
    return $tb <=> $ta;
});
uasort($bookingsHist, function ($a, $b) {
    $ta = strtotime((string)($a['returned_at'] ?? $a['rejected_at'] ?? $a['cancelled_at'] ?? $a['created_at'] ?? 'now'));
    $tb = strtotime((string)($b['returned_at'] ?? $b['rejected_at'] ?? $b['cancelled_at'] ?? $b['created_at'] ?? 'now'));
    return $tb <=> $ta;
});

$itemsCount = static function (array $row): int {
    $n = 0;
    foreach (($row['items'] ?? []) as $info) {
        if (is_array($info)) {
            $n += (int)($info['qty'] ?? 0);
        }
    }
    return $n;
};

$pageTitle = 'History';
$activeNav = 'history';
$layout    = 'wide';
require_once __DIR__ . '/../includes/header.php';
?>
<header class="page-head">
  <div>
    <span class="eyebrow">Cashier Console</span>
    <h1>History</h1>
    <p>A read-only archive of completed and cancelled orders, plus rejected, returned, and cancelled rental bookings.</p>
  </div>
</header>

<section class="card mb-4">
  <div class="card__head">
    <h2>Orders archive</h2>
    <span class="muted" style="font-size:13px"><?= count($ordersHist) ?> record<?= count($ordersHist) === 1 ? '' : 's' ?></span>
  </div>
  <div class="card__body" style="padding:0">
    <?php if (!$ordersHist): ?>
      <div class="empty" style="border:0;border-radius:0">
        <div class="empty__icon" aria-hidden="true">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8v4l3 3"/><circle cx="12" cy="12" r="10"/></svg>
        </div>
        <h3>No archived orders yet</h3>
        <p>Completed and cancelled orders will appear here.</p>
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
              <th>Cancelled / Completed</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($ordersHist as $id => $o):
              $st       = (string)($o['status'] ?? '');
              [$sLabel,$sCls] = order_status_label($st);
              $ps       = (string)($o['payment_status'] ?? '');
              [$pLabel,$pCls] = payment_status_label($ps);
              $custName = (string)($o['customer_name'] ?? $o['user_name'] ?? '');
              $rawTs    = $o['cancelled_at'] ?? $o['completed_at'] ?? $o['updated_at'] ?? $o['created_at'] ?? '';
              $ts       = $rawTs ? date('M j, Y \a\t g:i A', strtotime($rawTs)) : '';
              $count    = $itemsCount($o);
          ?>
            <tr>
              <td><strong>#<?= e(substr((string)$id, 0, 6)) ?></strong></td>
              <td>
                <?= e($custName ?: '—') ?>
                <?php if (!empty($o['contact'])): ?>
                  <br><small class="muted"><?= e($o['contact']) ?></small>
                <?php endif; ?>
              </td>
              <td><?= (int)$count ?> item<?= (int)$count === 1 ? '' : 's' ?></td>
              <td class="num"><strong><?= e(money((float)($o['total'] ?? 0))) ?></strong></td>
              <td><span class="badge <?= e($pCls) ?>"><?= e($pLabel) ?></span></td>
              <td><span class="badge <?= e($sCls) ?>"><?= e($sLabel) ?></span></td>
              <td>
                <small><?= e($ts ?: '—') ?></small>
                <?php if (!empty($o['cancel_note'])): ?>
                  <br><small class="muted">Note: <?= e($o['cancel_note']) ?></small>
                <?php endif; ?>
                <?php if (!empty($o['cancelled_by'])): ?>
                  <br><small class="muted">By: <?= e($o['cancelled_by']) ?></small>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="card">
  <div class="card__head">
    <h2>Bookings archive</h2>
    <span class="muted" style="font-size:13px"><?= count($bookingsHist) ?> record<?= count($bookingsHist) === 1 ? '' : 's' ?></span>
  </div>
  <div class="card__body" style="padding:0">
    <?php if (!$bookingsHist): ?>
      <div class="empty" style="border:0;border-radius:0">
        <div class="empty__icon" aria-hidden="true">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8v4l3 3"/><circle cx="12" cy="12" r="10"/></svg>
        </div>
        <h3>No archived bookings yet</h3>
        <p>Rejected, returned, and cancelled bookings will appear here.</p>
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
              <th>Payment</th>
              <th>Status</th>
              <th>Closed</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($bookingsHist as $id => $b):
              $st       = (string)($b['status'] ?? '');
              [$sLabel,$sCls] = booking_status_label($st);
              $ps       = (string)($b['payment_status'] ?? '');
              [$pLabel,$pCls] = payment_status_label($ps);
              $custName = (string)($b['user_name'] ?? $b['full_name'] ?? '');
              $rawTs    = $b['returned_at'] ?? $b['rejected_at'] ?? $b['cancelled_at'] ?? $b['created_at'] ?? '';
              $ts       = $rawTs ? date('M j, Y \a\t g:i A', strtotime($rawTs)) : '';
              $count    = $itemsCount($b);
              $by       = (string)($b['returned_by'] ?? $b['rejected_by'] ?? $b['cancelled_by'] ?? '');
          ?>
            <tr>
              <td>
                <strong>#<?= e(substr((string)$id, 0, 6)) ?></strong>
                <?php if (($b['user_email'] ?? '') === 'walk-in'): ?>
                  <br><span class="badge badge--gold" style="font-size:10px">Walk-in</span>
                <?php endif; ?>
              </td>
              <td>
                <?= e($custName ?: '—') ?>
                <?php if (!empty($b['contact'])): ?>
                  <br><small class="muted"><?= e($b['contact']) ?></small>
                <?php endif; ?>
              </td>
              <td><?= (int)$count ?> item<?= (int)$count === 1 ? '' : 's' ?></td>
              <td class="num"><strong><?= e(money((float)($b['total'] ?? 0))) ?></strong></td>
              <td><span class="badge <?= e($pCls) ?>"><?= e($pLabel) ?></span></td>
              <td><span class="badge <?= e($sCls) ?>"><?= e($sLabel) ?></span></td>
              <td>
                <small><?= e($ts ?: '—') ?></small>
                <?php if ($by !== ''): ?>
                  <br><small class="muted">By: <?= e($by) ?></small>
                <?php endif; ?>
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
