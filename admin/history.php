<?php
/**
 * admin/history.php — full order + booking history.
 * Read-only archive of ALL orders and ALL bookings, newest first.
 * Supports a single-date filter and a free-text search.
 */
require_once __DIR__ . '/../init.php';
require_admin();
security_headers();

use App\Models\Order;
use App\Models\Booking;

$q    = trim((string)($_GET['q'] ?? ''));
$date = trim((string)($_GET['date'] ?? ''));

if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = '';
$hasFilter = ($q !== '' || $date !== '');

$orders   = Order::raw();
$bookings = Booking::raw();

if ($hasFilter) {
    $needle = mb_strtolower($q);
    foreach ($orders as $id => $o) {
        if (!is_array($o)) { unset($orders[$id]); continue; }
        if ($date !== '' && substr((string)($o['created_at'] ?? 'now'), 0, 10) !== $date) {
            unset($orders[$id]); continue;
        }
        if ($q !== '') {
            $hay = mb_strtolower(implode(' ', [
                $o['customer_name'] ?? $o['user_name'] ?? '',
                $o['user_email'] ?? '', $o['contact'] ?? '', $id,
            ]));
            foreach (($o['items'] ?? []) as $it) {
                if (is_array($it)) $hay .= ' ' . mb_strtolower((string)($it['name'] ?? ''));
            }
            if (strpos($hay, $needle) === false) { unset($orders[$id]); continue; }
        }
    }
    foreach ($bookings as $id => $b) {
        if (!is_array($b)) { unset($bookings[$id]); continue; }
        if ($date !== '' && substr((string)($b['created_at'] ?? 'now'), 0, 10) !== $date) {
            unset($bookings[$id]); continue;
        }
        if ($q !== '') {
            $hay = mb_strtolower(implode(' ', [
                $b['user_name'] ?? $b['full_name'] ?? '',
                $b['user_email'] ?? '', $b['contact'] ?? '', $b['address'] ?? '', $id,
            ]));
            foreach (($b['items'] ?? []) as $it) {
                if (is_array($it)) $hay .= ' ' . mb_strtolower((string)($it['name'] ?? ''));
            }
            if (strpos($hay, $needle) === false) { unset($bookings[$id]); continue; }
        }
    }
}

uasort($orders, function ($a, $b) {
    $ta = strtotime((string)($a['created_at'] ?? 'now'));
    $tb = strtotime((string)($b['created_at'] ?? 'now'));
    return $tb <=> $ta;
});
uasort($bookings, function ($a, $b) {
    $ta = strtotime((string)($a['created_at'] ?? 'now'));
    $tb = strtotime((string)($b['created_at'] ?? 'now'));
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

<div class="page-head">
  <div class="page-head__row">
    <div>
      <span class="eyebrow">Control Center</span>
      <h1 class="mt-2">History</h1>
      <p>Every order and rent reservation on record — newest first.</p>
    </div>
  </div>
</div>

<form class="card mb-4" method="get" action="/admin/history.php" style="padding:16px 20px">
  <div class="row" style="gap:10px;flex-wrap:wrap;align-items:flex-end">
    <div style="flex:1;min-width:220px">
      <label class="micro" for="fq" style="display:block;margin-bottom:4px">Search</label>
      <input class="input" id="fq" type="text" name="q" value="<?= e($q) ?>"
             placeholder="Customer, email, contact, item or order #" style="min-height:38px">
    </div>
    <div>
      <label class="micro" for="fdate" style="display:block;margin-bottom:4px">Date</label>
      <input class="input" id="fdate" type="date" name="date" value="<?= e($date) ?>" style="min-height:38px;width:auto">
    </div>
    <div class="row" style="gap:6px">
      <button class="btn btn--gold btn--sm" type="submit">Filter</button>
      <?php if ($hasFilter): ?><a class="btn btn--ghost btn--sm" href="/admin/history.php">Reset</a><?php endif; ?>
    </div>
  </div>
</form>

<?php if ($hasFilter): ?>
  <p class="muted" style="font-size:13px;margin-top:6px">
    Showing <?= count($orders) + count($bookings) ?> result<?= count($orders) + count($bookings) === 1 ? '' : 's' ?>
    <?php if ($date !== ''): ?> for <?= e(date('M j, Y', strtotime($date))) ?><?php endif; ?>
    <?php if ($q !== ''): ?> matching &ldquo;<?= e($q) ?>&rdquo;<?php endif; ?>
  </p>
<?php endif; ?>

<section class="card mb-4">
  <div class="card__head">
    <h2>Orders</h2>
    <span class="muted" style="font-size:13px"><?= count($orders) ?> record<?= count($orders) === 1 ? '' : 's' ?></span>
  </div>
  <div class="table-wrap" style="border:0;border-radius:0;">
    <table class="tbl">
      <thead>
        <tr>
          <th>Order</th>
          <th>Customer</th>
          <th>Items</th>
          <th class="num">Total</th>
          <th>Payment</th>
          <th>Status</th>
          <th>Placed</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$orders): ?>
        <tr><td colspan="7" class="muted t-center">No orders yet.</td></tr>
      <?php else: foreach ($orders as $id => $o):
          $st       = (string)($o['status'] ?? '');
          [$sLabel, $sCls] = order_status_label($st);
          $ps       = (string)($o['payment_status'] ?? '');
          [$pLabel, $pCls] = payment_status_label($ps);
          $custName = (string)($o['customer_name'] ?? $o['user_name'] ?? '');
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
          <td class="micro"><?= e(date('M j, Y g:i A', strtotime((string)($o['created_at'] ?? 'now')))) ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="card">
  <div class="card__head">
    <h2>Bookings</h2>
    <span class="muted" style="font-size:13px"><?= count($bookings) ?> record<?= count($bookings) === 1 ? '' : 's' ?></span>
  </div>
  <div class="table-wrap" style="border:0;border-radius:0;">
    <table class="tbl">
      <thead>
        <tr>
          <th>Booking</th>
          <th>Customer</th>
          <th>Items</th>
          <th class="num">Total</th>
          <th>Payment</th>
          <th>Status</th>
          <th>Appointment</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$bookings): ?>
        <tr><td colspan="7" class="muted t-center">No bookings yet.</td></tr>
      <?php else: foreach ($bookings as $id => $b):
          $st       = (string)($b['status'] ?? '');
          [$sLabel, $sCls] = booking_status_label($st);
          $ps       = (string)($b['payment_status'] ?? '');
          [$pLabel, $pCls] = payment_status_label($ps);
          $custName = (string)($b['user_name'] ?? $b['full_name'] ?? '');
          $count    = $itemsCount($b);
          $appt     = (string)($b['appointment_time'] ?? '');
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
          <td class="micro"><?= $appt ? e(date('M j, Y g:i A', strtotime($appt))) : '—' ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
