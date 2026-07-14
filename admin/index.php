<?php
/**
 * admin/index.php — Dashboard: KPI strip, 7-day sales chart, recent activity.
 */
require_once __DIR__ . '/../init.php';
require_admin();

$db        = getDB();
/* P1: wrap Firebase reads in a per-request cache (30s TTL) so the dashboard
   doesn't re-fetch the entire DB on every load / refresh within the window. */
$orders    = cache_remember('admin_orders',    30, fn() => rows($db->retrieve('/orders')));
$bookings  = cache_remember('admin_bookings',  30, fn() => rows($db->retrieve('/bookings')));
$products  = cache_remember('admin_products',  30, fn() => rows($db->retrieve('/products')));
$rentItems = cache_remember('admin_rent_items', 30, fn() => rows($db->retrieve('/rent_items')));

/* ---------- KPIs ---------- */
$totalOrders  = count($orders);
$pendingOrders = 0;
$totalSales    = 0.0;
foreach ($orders as $o) {
    if (!is_array($o)) continue;
    $st = (string) ($o['status'] ?? '');
    if ($st === 'pending') $pendingOrders++;
    if ((string) ($o['payment_status'] ?? '') === 'paid') {
        $totalSales += (float) ($o['total'] ?? 0);
    }
}
$totalBookings = count($bookings);
$activeRent    = 0;
foreach ($rentItems as $r) {
    if (is_array($r) && (int) ($r['quantity'] ?? 0) > 0) $activeRent++;
}
$lowStock = 0;
foreach ($products as $p) {
    if (is_array($p) && (int) ($p['stock'] ?? 0) <= 5) $lowStock++;
}

/* ---------- 7-day sales chart ---------- */
$dayTotals = [];
$dayLabels = [];
for ($i = 6; $i >= 0; $i--) {
    $d  = strtotime("-$i days");
    $key  = date('Y-m-d', $d);
    $label = date('D', $d);
    $dayTotals[$key] = 0.0;
    $dayLabels[$key] = $label;
}
foreach ($orders as $o) {
    if (!is_array($o)) continue;
    if ((string) ($o['payment_status'] ?? '') !== 'paid') continue;
    $created = (string) ($o['created_at'] ?? '');
    $day = substr($created, 0, 10);
    if (isset($dayTotals[$day])) {
        $dayTotals[$day] += (float) ($o['total'] ?? 0);
    }
}
$maxDay = max($dayTotals) ?: 1.0;

/* SVG bar chart geometry */
$chartH   = 200;
$chartPad = 36;   // top padding for value labels + bottom for day labels
$barW     = 56;
$gap      = 26;
$days     = array_keys($dayTotals);
$chartW   = count($days) * ($barW + $gap) + $gap;

/* ---------- Recent orders (last 8 by created_at desc) ---------- */
$recentOrders = $orders;
usort($recentOrders, function ($a, $b) {
    $ta = strtotime((string) ($a['created_at'] ?? '')) ?: 0;
    $tb = strtotime((string) ($b['created_at'] ?? '')) ?: 0;
    return $tb <=> $ta;
});
$recentOrders = array_slice($recentOrders, 0, 8, true);

/* ---------- Recent bookings (last 5) ---------- */
$recentBookings = $bookings;
usort($recentBookings, function ($a, $b) {
    $ta = strtotime((string) ($a['created_at'] ?? '')) ?: 0;
    $tb = strtotime((string) ($b['created_at'] ?? '')) ?: 0;
    return $tb <=> $ta;
});
$recentBookings = array_slice($recentBookings, 0, 5, true);

$pageTitle = 'Dashboard';
$activeNav = 'dash';
$layout    = 'wide';
require_once __DIR__ . '/../includes/header.php';
?>
<style>
  /* Page-local chart polish (foundation .stat / .grid--stat used for KPIs) */
  .stat__hint { font-size:12px; color:var(--muted); margin-top:8px; position:relative; z-index:1; }
  .stat__hint.danger { color:var(--danger); font-weight:600; }
  .stat__value.is-danger { color:var(--danger); }

  .chart-card .card__body { padding:24px 26px 12px; }
  .chart-svg { width:100%; height:auto; display:block; }
  .chart-svg .bar { transition:opacity .15s ease; cursor:pointer; }
  .chart-svg .bar:hover { opacity:.82; }

  .grid--2 { display:grid; grid-template-columns:1.4fr 1fr; gap:22px; }
  @media (max-width:980px) { .grid--2 { grid-template-columns:1fr; } }
  .micro { font-size:12px; color:var(--muted); }
</style>

<div class="page-head">
  <div class="page-head__row">
    <div>
      <span class="eyebrow">Control Center</span>
      <h1 class="mt-2">Dashboard</h1>
      <p>A live snapshot of your floor — sales, orders, rentals and stock.</p>
    </div>
    <div class="row">
      <a class="btn btn--outline btn--sm" href="/admin/reports.php">View reports</a>
      <a class="btn btn--gold btn--sm" href="/admin/products.php">Manage products</a>
    </div>
  </div>
</div>

<!-- KPI strip -->
<div class="grid grid--stat">
  <div class="stat">
    <div class="stat__label">Total orders</div>
    <div class="stat__value"><?= $totalOrders ?></div>
    <div class="stat__hint"><?= $pendingOrders ?> pending</div>
  </div>
  <div class="stat">
    <div class="stat__label">Total sales</div>
    <div class="stat__value"><?= e(money($totalSales)) ?></div>
    <div class="stat__hint">From paid orders</div>
  </div>
  <div class="stat">
    <div class="stat__label">Total bookings</div>
    <div class="stat__value"><?= $totalBookings ?></div>
    <div class="stat__hint">Rent reservations</div>
  </div>
  <div class="stat">
    <div class="stat__label">Active rent items</div>
    <div class="stat__value"><?= $activeRent ?></div>
    <div class="stat__hint">With stock on hand</div>
  </div>
  <div class="stat">
    <div class="stat__label">Low-stock products</div>
    <div class="stat__value<?= $lowStock ? ' is-danger' : '' ?>"><?= $lowStock ?></div>
    <div class="stat__hint <?= $lowStock ? 'danger' : '' ?>">Stock ≤ 5 units</div>
  </div>
</div>

<!-- 7-day sales chart -->
<section class="section">
  <div class="card chart-card">
    <div class="card__head">
      <div>
        <h2>Last 7 days of sales</h2>
        <small class="micro">Daily totals of paid orders (<?= e(BRAND_NAME) ?>)</small>
      </div>
      <a class="btn btn--ghost btn--sm" href="/admin/reports.php">Open reports →</a>
    </div>
    <div class="card__body">
      <svg class="chart-svg" viewBox="0 0 <?= $chartW ?> <?= $chartH + $chartPad ?>"
           role="img" aria-label="Bar chart of daily sales for the last seven days">
        <defs>
          <linearGradient id="goldGrad" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%"  stop-color="#d8a94e"/>
            <stop offset="100%" stop-color="#a9751f"/>
          </linearGradient>
          <linearGradient id="trackGrad" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%"  stop-color="#efe8da"/>
            <stop offset="100%" stop-color="#f6f2ea"/>
          </linearGradient>
        </defs>
        <!-- baseline -->
        <line x1="<?= $gap/2 ?>" y1="<?= $chartH ?>" x2="<?= $chartW - $gap/2 ?>" y2="<?= $chartH ?>"
              stroke="#e6dfd1" stroke-width="1"/>
        <?php foreach ($days as $i => $day):
            $val   = $dayTotals[$day];
            $h     = $val > 0 ? max(4, ($val / $maxDay) * ($chartH - 18)) : 2;
            $x     = $gap + $i * ($barW + $gap);
            $y     = $chartH - $h;
            $label = $dayLabels[$day];
            $short = date('M j', strtotime($day));
        ?>
          <rect x="<?= $x ?>" y="0" width="<?= $barW ?>" height="<?= $chartH ?>"
                fill="url(#trackGrad)" rx="6" opacity=".45"/>
          <rect class="bar" x="<?= $x ?>" y="<?= $y ?>" width="<?= $barW ?>" height="<?= $h ?>"
                fill="url(#goldGrad)" rx="6">
            <title><?= e($label . ', ' . $short . ' — ' . money($val)) ?></title>
          </rect>
          <?php if ($val > 0): ?>
            <text x="<?= $x + $barW/2 ?>" y="<?= max(14, $y - 6) ?>" text-anchor="middle"
                  font-family="Inter, sans-serif" font-size="11" font-weight="600" fill="#4b4136">
              <?= e('₱' . number_format($val, 0)) ?>
            </text>
          <?php endif; ?>
          <text x="<?= $x + $barW/2 ?>" y="<?= $chartH + 18 ?>" text-anchor="middle"
                font-family="Inter, sans-serif" font-size="11" fill="#8a7f70">
            <?= e($label) ?>
          </text>
        <?php endforeach; ?>
      </svg>
    </div>
  </div>
</section>

<!-- Recent activity -->
<section class="section">
  <div class="grid--2">
    <div class="card">
      <div class="card__head">
        <div><h2>Recent orders</h2><small class="micro">Latest 8 orders</small></div>
        <a class="btn btn--ghost btn--sm" href="/admin/reports.php">All orders →</a>
      </div>
      <div class="table-wrap" style="border:0;border-radius:0;">
        <table class="tbl">
          <thead>
            <tr><th>Customer</th><th>Date</th><th class="num">Total</th><th>Payment</th><th>Status</th></tr>
          </thead>
          <tbody>
          <?php if (!$recentOrders): ?>
            <tr><td colspan="5" class="muted t-center">No orders yet.</td></tr>
          <?php else: foreach ($recentOrders as $id => $o):
                [$pl, $pc] = payment_status_label((string) ($o['payment_status'] ?? ''));
                [$ol, $oc] = order_status_label((string) ($o['status'] ?? ''));
          ?>
            <tr>
              <td>
                <strong><?= e($o['user_name'] ?? 'Guest') ?></strong><br>
                <small class="micro"><?= e($o['user_email'] ?? '—') ?></small>
              </td>
              <td class="micro"><?= e(date('M j, Y g:i A', strtotime((string) ($o['created_at'] ?? 'now')))) ?></td>
              <td class="num"><?= e(money((float) ($o['total'] ?? 0))) ?></td>
              <td><span class="badge <?= e($pc) ?>"><?= e($pl) ?></span></td>
              <td><span class="badge <?= e($oc) ?>"><?= e($ol) ?></span></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="card__head">
        <div><h2>Recent bookings</h2><small class="micro">Latest 5 reservations</small></div>
        <a class="btn btn--ghost btn--sm" href="/admin/bookings.php">All bookings →</a>
      </div>
      <div class="table-wrap" style="border:0;border-radius:0;">
        <table class="tbl">
          <thead>
            <tr><th>Customer</th><th>Appt.</th><th>Status</th></tr>
          </thead>
          <tbody>
          <?php if (!$recentBookings): ?>
            <tr><td colspan="3" class="muted t-center">No bookings yet.</td></tr>
          <?php else: foreach ($recentBookings as $id => $b):
                [$bl, $bc] = booking_status_label((string) ($b['status'] ?? ''));
                $appt = (string) ($b['appointment_time'] ?? '');
          ?>
            <tr>
              <td>
                <strong><?= e($b['user_name'] ?? 'Guest') ?></strong><br>
                <small class="micro"><?= e($b['user_email'] ?? '—') ?></small>
              </td>
              <td class="micro"><?= $appt ? e(date('M j, g:i A', strtotime($appt))) : '—' ?></td>
              <td><span class="badge <?= e($bc) ?>"><?= e($bl) ?></span></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
