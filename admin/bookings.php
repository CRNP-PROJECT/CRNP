<?php
/**
 * admin/bookings.php — Rent reservations overview.
 */
require_once __DIR__ . '/../init.php';
require_admin();
use App\Models\Booking;

/* ---------- List ---------- */
$page  = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$bookingPage = Booking::paginate($page, $perPage);
$bookings  = $bookingPage['data'];
$totalBookings = $bookingPage['total'];
$pages = $bookingPage['pages'];

uasort($bookings, function ($a, $b) {
    $ta = strtotime((string) ($a['created_at'] ?? '')) ?: 0;
    $tb = strtotime((string) ($b['created_at'] ?? '')) ?: 0;
    return $tb <=> $ta;
});

function items_summary($items): string {
    if (!is_array($items) || !$items) return '—';
    $parts = [];
    foreach ($items as $entry) {
        if (!is_array($entry)) continue;
        $q   = (int) ($entry['qty'] ?? 0);
        $nm  = (string) ($entry['name'] ?? 'Item');
        $parts[] = $q . '× ' . $nm;
        if (count($parts) >= 2) break;
    }
    $total = is_array($items) ? count($items) : 0;
    $more  = $total - count($parts);
    $s = implode(', ', $parts);
    if ($more > 0) $s .= ' +' . $more . ' more';
    return $s;
}

$pageTitle = 'Bookings';
$activeNav = 'bookings';
$layout    = 'wide';
require_once __DIR__ . '/../includes/header.php';
?>
<style>
  .micro { font-size:12px; color:var(--muted); }
  .layout-2 { display:grid; grid-template-columns:1.6fr 1fr; gap:24px; align-items:start; }
  @media (max-width:980px) { .layout-2 { grid-template-columns:1fr; } }
  .qty-cell { display:flex; align-items:center; gap:10px; }
  .qty-cell .stock { font-size:11px; color:var(--muted); }
  .qty-cell .stock.low { color:var(--danger); font-weight:600; }
  .qty-input { width:74px; }
  .item-row { display:flex; align-items:center; gap:12px; padding:10px 0; border-bottom:1px solid var(--line-2); }
  .item-row:last-child { border-bottom:0; }
  .item-row img { width:42px; height:42px; border-radius:9px; object-fit:cover; border:1px solid var(--line); background:var(--bg-2); }
  .item-row .meta { flex:1; }
  .item-row .meta strong { display:block; color:var(--ink); font-size:14px; }
  .item-row .meta small { color:var(--muted); }
  details summary { cursor:pointer; list-style:none; }
  details summary::-webkit-details-marker { display:none; }
  .booking-details { background:var(--surface-2); border-radius:var(--radius-sm); padding:14px; margin-top:8px; }
  .booking-details dl { display:grid; grid-template-columns:auto 1fr; gap:6px 14px; margin:0; font-size:13px; }
  .booking-details dt { color:var(--muted); }
  .booking-details dd { margin:0; color:var(--ink); }
</style>

<div class="page-head">
  <div class="page-head__row">
    <div>
      <span class="eyebrow">Rentals</span>
      <h1 class="mt-2">Bookings</h1>
      <p>Review all rent reservations.</p>
    </div>
  </div>
</div>

<div class="card">
    <div class="card__head">
      <div><h2>All bookings</h2><small><?= $totalBookings ?> reservation(s) &middot; page <?= $page ?> of <?= $pages ?></small></div>
      <?php if ($pages > 1): ?>
        <div class="row" style="gap:6px">
          <?php if ($page > 1): ?><a class="btn btn--ghost btn--sm" href="?page=<?= $page - 1 ?>">&larr; Prev</a><?php endif; ?>
          <?php if ($page < $pages): ?><a class="btn btn--ghost btn--sm" href="?page=<?= $page + 1 ?>">Next &rarr;</a><?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
    <div class="table-wrap" style="border:0;border-radius:0;">
      <table class="tbl">
        <thead>
          <tr>
            <th>Customer</th><th>Items</th><th>Appointment</th><th>Return</th>
            <th class="num">Total</th><th>Status</th><th></th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$bookings): ?>
          <tr><td colspan="7" class="muted t-center">No bookings yet.</td></tr>
        <?php else: foreach ($bookings as $bid => $b):
            [$bl, $bc] = booking_status_label((string) ($b['status'] ?? ''));
            [$pl, $pc] = payment_status_label((string) ($b['payment_status'] ?? ''));
            $appt = (string) ($b['appointment_time'] ?? '');
            $ret  = (string) ($b['return_time'] ?? '');
        ?>
          <tr>
            <td>
              <strong><?= e($b['user_name'] ?? 'Guest') ?></strong><br>
              <small class="micro"><?= e($b['user_email'] ?? '—') ?></small>
            </td>
            <td class="micro"><?= e(items_summary($b['items'] ?? [])) ?></td>
            <td class="micro"><?= $appt ? e(date('M j, Y g:i A', strtotime($appt))) : '—' ?></td>
            <td class="micro"><?= $ret ? e(date('M j, g:i A', strtotime($ret))) : '—' ?></td>
            <td class="num"><?= e(money((float) ($b['total'] ?? 0))) ?></td>
            <td><span class="badge <?= e($bc) ?>"><?= e($bl) ?></span></td>
            <td>
              <details>
                <summary class="btn btn--ghost btn--sm">View</summary>
                <div class="booking-details">
                  <dl>
                    <dt>Booking ID</dt><dd><code><?= e((string) $bid) ?></code></dd>
                    <dt>Contact</dt><dd><?= e($b['contact'] ?? '—') ?></dd>
                    <dt>Address</dt><dd><?= e($b['address'] ?? '—') ?></dd>
                    <dt>Payment</dt><dd><span class="badge <?= e($pc) ?>"><?= e($pl) ?></span> · <?php [$pmLabel, $pmCls] = payment_method_label((string) ($b['payment_method'] ?? 'counter')); ?><span class="badge <?= e($pmCls) ?>"><?= e($pmLabel) ?></span></dd>
                    <dt>Created by</dt><dd><?= e(ucfirst((string) ($b['created_by'] ?? '—'))) ?></dd>
                    <dt>Created</dt><dd><?= e((string) ($b['created_at'] ?? '—')) ?></dd>
                  </dl>
                  <?php if (!empty($b['items']) && is_array($b['items'])): ?>
                    <hr style="margin:10px 0;">
                    <strong style="font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);">Items</strong>
                    <ul style="margin:6px 0 0;padding-left:18px;font-size:13px;">
                      <?php foreach ($b['items'] as $itemId => $it): ?>
                        <li><?= e((string) ($it['name'] ?? 'Item')) ?> —
                          <?= (int) ($it['qty'] ?? 0) ?> × <?= e(money((float) ($it['price'] ?? 0))) ?>
                          = <strong><?= e(money((float) ($it['subtotal'] ?? 0))) ?></strong></li>
                      <?php endforeach; ?>
                    </ul>
                  <?php endif; ?>
                </div>
              </details>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
