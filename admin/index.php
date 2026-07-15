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
$totalOrders   = count($orders);
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

/* ---------- Today's stats ---------- */
$today          = date('Y-m-d');
$todayOrders    = 0;
$todaySales     = 0.0;
$todayBookings  = 0;
foreach ($orders as $o) {
    if (!is_array($o)) continue;
    $d = substr((string) ($o['created_at'] ?? ''), 0, 10);
    if ($d === $today) {
        $todayOrders++;
        if ((string) ($o['payment_status'] ?? '') === 'paid') {
            $todaySales += (float) ($o['total'] ?? 0);
        }
    }
}
foreach ($bookings as $b) {
    if (!is_array($b)) continue;
    $d = substr((string) ($b['created_at'] ?? ''), 0, 10);
    if ($d === $today) $todayBookings++;
}

/* ---------- Best-selling by category (pie chart data) ---------- */
$categorySales = []; // category => total qty sold
foreach ($orders as $o) {
    if (!is_array($o)) continue;
    if (in_array(($o['status'] ?? ''), ['cancelled', 'cashier_cancelled'], true)) continue;
    foreach (($o['items'] ?? []) as $pid => $info) {
        if (!is_array($info)) continue;
        $qty = (int) ($info['qty'] ?? 0);
        if ($qty <= 0) continue;
        $cat = (string) ($products[$pid]['category'] ?? 'Uncategorized');
        $categorySales[$cat] = ($categorySales[$cat] ?? 0) + $qty;
    }
}
arsort($categorySales);
$categorySales = array_slice($categorySales, 0, 8, true);

/* ---------- Bookings by status (column chart data) ---------- */
$bookingStatuses = [];
foreach ($bookings as $b) {
    if (!is_array($b)) continue;
    $st = (string) ($b['status'] ?? 'unknown');
    [$label] = booking_status_label($st);
    $bookingStatuses[$label] = ($bookingStatuses[$label] ?? 0) + 1;
}
arsort($bookingStatuses);
$maxBookingStatus = max($bookingStatuses) ?: 1;

/* ---------- Best-selling rent items ---------- */
$rentItemSales = [];
foreach ($bookings as $b) {
    if (!is_array($b)) continue;
    if (in_array(($b['status'] ?? ''), ['cancelled', 'rejected'], true)) continue;
    foreach (($b['items'] ?? []) as $rid => $info) {
        if (!is_array($info)) continue;
        $qty = (int) ($info['qty'] ?? 0);
        if ($qty <= 0) continue;
        $name = (string) ($rentItems[$rid]['name'] ?? $info['name'] ?? 'Item');
        $rentItemSales[$name] = ($rentItemSales[$name] ?? 0) + $qty;
    }
}
arsort($rentItemSales);
$rentItemSales = array_slice($rentItemSales, 0, 10, true);
$maxRentQty = max($rentItemSales) ?: 1;

/* ---------- SVG pie chart helper ---------- */
$pieColors = ['#d8a94e','#a9751f','#2d6a4f','#40916c','#bc4749','#e07a5f','#3d405b','#81b29a'];
function svgPie(array $data, int $size = 180): string {
    global $pieColors;
    $total = array_sum($data);
    if ($total <= 0) return '';
    $cx = $size / 2; $cy = $size / 2; $r = $size / 2 - 4;
    $svg = '<svg viewBox="0 0 ' . $size . ' ' . $size . '" width="' . $size . '" height="' . $size . '" role="img" aria-label="Pie chart">';
    $start = -90;
    $i = 0;
    foreach ($data as $label => $val) {
        if ($val <= 0) { $i++; continue; }
        $pct = $val / $total;
        $angle = $pct * 360;
        $end = $start + $angle;
        $large = $angle > 180 ? 1 : 0;
        $sr = deg2rad($start); $er = deg2rad($end);
        $x1 = $cx + $r * cos($sr); $y1 = $cy + $r * sin($sr);
        $x2 = $cx + $r * cos($er); $y2 = $cy + $r * sin($er);
        $color = $pieColors[$i % count($pieColors)];
        $svg .= '<path d="M' . $cx . ',' . $cy . ' L' . $x1 . ',' . $y1 . ' A' . $r . ',' . $r . ' 0 ' . $large . ',1 ' . $x2 . ',' . $y2 . ' Z" fill="' . $color . '">';
        $svg .= '<title>' . htmlspecialchars($label) . ': ' . $val . ' (' . round($pct * 100) . '%)</title>';
        $svg .= '</path>';
        $start = $end;
        $i++;
    }
    $svg .= '</svg>';
    return $svg;
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

/* ---------- Payment method breakdown (pie) ---------- */
$paymentMethods = [];
foreach ($orders as $o) {
    if (!is_array($o)) continue;
    if ((string) ($o['payment_status'] ?? '') !== 'paid') continue;
    $pm = (string) ($o['payment_method'] ?? 'counter');
    $label = $pm === 'gcash' ? 'GCash' : 'Counter';
    $paymentMethods[$label] = ($paymentMethods[$label] ?? 0) + 1;
}
arsort($paymentMethods);

/* ---------- Order status distribution (pie) ---------- */
$orderStatuses = [];
foreach ($orders as $o) {
    if (!is_array($o)) continue;
    $st = (string) ($o['status'] ?? 'unknown');
    [$label] = order_status_label($st);
    $orderStatuses[$label] = ($orderStatuses[$label] ?? 0) + 1;
}
arsort($orderStatuses);

/* ---------- Peak hours bar chart ---------- */
$peakHours = array_fill(0, 24, 0);
foreach ($orders as $o) {
    if (!is_array($o)) continue;
    $created = (string) ($o['created_at'] ?? '');
    if ($created !== '') {
        $hour = (int) date('G', strtotime($created));
        $peakHours[$hour]++;
    }
}
$maxPeak = max($peakHours) ?: 1;

/* ---------- Top 10 products (horizontal bar) ---------- */
$productSales = [];
foreach ($orders as $o) {
    if (!is_array($o)) continue;
    if (in_array(($o['status'] ?? ''), ['cancelled', 'cashier_cancelled'], true)) continue;
    foreach (($o['items'] ?? []) as $pid => $info) {
        if (!is_array($info)) continue;
        $qty = (int) ($info['qty'] ?? 0);
        if ($qty <= 0) continue;
        $name = (string) ($products[$pid]['name'] ?? $info['name'] ?? 'Item');
        $productSales[$name] = ($productSales[$name] ?? 0) + $qty;
    }
}
arsort($productSales);
$productSales = array_slice($productSales, 0, 10, true);
$maxProdQty  = max($productSales) ?: 1;

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
  .grid--pie { display:grid; grid-template-columns:1fr 1fr; gap:22px; }
  @media (max-width:980px) { .grid--pie { grid-template-columns:1fr; } }
  .micro { font-size:12px; color:var(--muted); }
  .pie-wrap { display:flex; align-items:center; gap:24px; flex-wrap:wrap; padding:8px 0; }
  .pie-legend { list-style:none; padding:0; margin:0; }
  .pie-legend li { display:flex; align-items:center; gap:8px; font-size:13px; margin-bottom:6px; }
  .pie-legend__dot { width:12px; height:12px; border-radius:3px; flex-shrink:0; }
  .today-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; }
  @media (max-width:640px) { .today-grid { grid-template-columns:1fr; } }
  .today-card { padding:18px 20px; border:1px solid var(--line,#e6dfd1); border-radius:12px; background:var(--surface,#fff); }
  .today-card__label { font-size:12px; text-transform:uppercase; letter-spacing:.06em; color:var(--muted); margin-bottom:4px; }
  .today-card__value { font-family:var(--serif); font-size:1.6rem; font-weight:700; }
  .today-card__hint { font-size:12px; color:var(--muted); margin-top:4px; }
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

<!-- Today's stats -->
<section class="section">
  <div class="today-grid">
    <div class="today-card">
      <div class="today-card__label">Today&rsquo;s orders</div>
      <div class="today-card__value"><?= $todayOrders ?></div>
      <div class="today-card__hint"><?= e(date('l, M j, Y')) ?></div>
    </div>
    <div class="today-card">
      <div class="today-card__label">Today&rsquo;s sales</div>
      <div class="today-card__value"><?= e(money($todaySales)) ?></div>
      <div class="today-card__hint">From paid orders today</div>
    </div>
    <div class="today-card">
      <div class="today-card__label">Today&rsquo;s bookings</div>
      <div class="today-card__value"><?= $todayBookings ?></div>
      <div class="today-card__hint">New reservations today</div>
    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════════════════
     ORDERS
     ═══════════════════════════════════════════════════════ -->

<!-- Orders: Best-selling by category + Payment methods -->
<section class="section">
  <div class="section-head">
    <span class="eyebrow">Orders</span>
    <h2>Sales &amp; product insights</h2>
  </div>
  <div class="grid--pie">
    <div class="card chart-card">
      <div class="card__head">
        <div>
          <h2>Best-selling by category</h2>
          <small class="micro">Total items sold across all orders</small>
        </div>
      </div>
      <div class="card__body">
        <?php if (!$categorySales): ?>
          <div class="muted" style="padding:20px 0">No sales data yet.</div>
        <?php else: ?>
          <div class="pie-wrap">
            <?= svgPie($categorySales) ?>
            <ul class="pie-legend">
              <?php $ci = 0; foreach ($categorySales as $cat => $qty): ?>
                <li>
                  <span class="pie-legend__dot" style="background:<?= $pieColors[$ci % count($pieColors)] ?>"></span>
                  <span><?= e($cat) ?>: <strong><?= $qty ?></strong> sold</span>
                </li>
              <?php $ci++; endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card chart-card">
      <div class="card__head">
        <div>
          <h2>Payment methods</h2>
          <small class="micro">Paid orders by payment type</small>
        </div>
      </div>
      <div class="card__body">
        <?php if (!$paymentMethods): ?>
          <div class="muted" style="padding:20px 0">No paid orders yet.</div>
        <?php else: ?>
          <div class="pie-wrap">
            <?= svgPie($paymentMethods) ?>
            <ul class="pie-legend">
              <?php $pi = 0; foreach ($paymentMethods as $label => $count): ?>
                <li>
                  <span class="pie-legend__dot" style="background:<?= $pieColors[$pi % count($pieColors)] ?>"></span>
                  <span><?= e($label) ?>: <strong><?= $count ?></strong> orders</span>
                </li>
              <?php $pi++; endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<!-- Orders: Order status + Peak hours -->
<section class="section">
  <div class="grid--pie">
    <div class="card chart-card">
      <div class="card__head">
        <div>
          <h2>Order status</h2>
          <small class="micro">All-time order status breakdown</small>
        </div>
      </div>
      <div class="card__body">
        <?php if (!$orderStatuses): ?>
          <div class="muted" style="padding:20px 0">No orders yet.</div>
        <?php else: ?>
          <div class="pie-wrap">
            <?= svgPie($orderStatuses) ?>
            <ul class="pie-legend">
              <?php $sti = 0; foreach ($orderStatuses as $label => $count): ?>
                <li>
                  <span class="pie-legend__dot" style="background:<?= $pieColors[$sti % count($pieColors)] ?>"></span>
                  <span><?= e($label) ?>: <strong><?= $count ?></strong></span>
                </li>
              <?php $sti++; endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card chart-card">
      <div class="card__head">
        <div>
          <h2>Peak hours</h2>
          <small class="micro">Order volume by hour of day</small>
        </div>
      </div>
      <div class="card__body">
        <?php
          $phW = 480; $phH = 160; $phPadL = 32; $phPadB = 24;
          $phPlotW = $phW - $phPadL - 8; $phPlotH = $phH - $phPadB - 4;
          $phBarW = max(4, floor($phPlotW / 24) - 2);
        ?>
        <div class="scroll-x">
          <svg class="chart-svg" viewBox="0 0 <?= $phW ?> <?= $phH + $phPadB ?>"
               role="img" aria-label="Bar chart of order volume by hour">
            <?php for ($gi = 0; $gi <= 3; $gi++):
                $gy = 4 + $phPlotH - ($gi / 3) * $phPlotH;
                $gv = intval(($maxPeak / 3) * $gi);
            ?>
              <line x1="<?= $phPadL ?>" y1="<?= $gy ?>" x2="<?= $phW - 8 ?>" y2="<?= $gy ?>"
                    stroke="#e6dfd1" stroke-width="1" stroke-dasharray="<?= $gi === 0 ? '0' : '3,3' ?>"/>
              <text x="<?= $phPadL - 4 ?>" y="<?= $gy + 3 ?>" text-anchor="end"
                    font-family="Inter,sans-serif" font-size="8" fill="#8a7f70"><?= $gv ?></text>
            <?php endfor; ?>
            <?php for ($h = 0; $h < 24; $h++):
                $x = $phPadL + $h * ($phBarW + 2);
                $val = $peakHours[$h];
                $bh = $maxPeak > 0 ? ($val / $maxPeak) * $phPlotH : 0;
            ?>
              <rect x="<?= $x ?>" y="<?= 4 + $phPlotH - max(1, $bh) ?>" width="<?= $phBarW ?>" height="<?= max(1, $bh) ?>"
                    fill="#d8a94e" rx="2" opacity=".9">
                <title><?= $h === 0 ? '12 AM' : ($h < 12 ? $h . ' AM' : ($h === 12 ? '12 PM' : ($h - 12) . ' PM')) ?>: <?= $val ?> order<?= $val === 1 ? '' : 's' ?></title>
              </rect>
              <?php if ($h % 3 === 0): ?>
                <text x="<?= $x + $phBarW / 2 ?>" y="<?= $phH + 14 ?>" text-anchor="middle"
                      font-family="Inter,sans-serif" font-size="8" fill="#8a7f70">
                  <?= $h === 0 ? '12a' : ($h < 12 ? $h . 'a' : ($h === 12 ? '12p' : ($h - 12) . 'p')) ?>
                </text>
              <?php endif; ?>
            <?php endfor; ?>
          </svg>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Orders: Top 10 products -->
<section class="section">
  <div class="card chart-card">
    <div class="card__head">
      <div>
        <h2>Top 10 products</h2>
        <small class="micro">Best-selling items by quantity</small>
      </div>
    </div>
    <div class="card__body">
      <?php if (!$productSales): ?>
        <div class="muted" style="padding:20px 0">No sales data yet.</div>
      <?php else: ?>
        <?php
          $tpH = max(180, count($productSales) * 28 + 20);
          $tpPadL = 120; $tpPadR = 50;
          $tpBarH = 18; $tpGap = 8;
          $tpPlotW = 300;
        ?>
        <div class="scroll-x">
          <svg class="chart-svg" viewBox="0 0 <?= $tpPadL + $tpPlotW + $tpPadR ?> <?= $tpH ?>"
               role="img" aria-label="Top 10 best-selling products">
            <?php $ti = 0; foreach ($productSales as $name => $qty):
                $y = 10 + $ti * ($tpBarH + $tpGap);
                $bw = $maxProdQty > 0 ? ($qty / $maxProdQty) * $tpPlotW : 0;
                $displayName = mb_strlen($name) > 16 ? mb_substr($name, 0, 14) . '…' : $name;
            ?>
              <text x="<?= $tpPadL - 6 ?>" y="<?= $y + $tpBarH / 2 + 4 ?>" text-anchor="end"
                    font-family="Inter,sans-serif" font-size="11" fill="#4b4136"><?= e($displayName) ?></text>
              <rect x="<?= $tpPadL ?>" y="<?= $y ?>" width="<?= max(2, $bw) ?>" height="<?= $tpBarH ?>"
                    fill="#d8a94e" rx="4" opacity=".9">
                <title><?= e($name) ?>: <?= $qty ?> sold</title>
              </rect>
              <text x="<?= $tpPadL + max(2, $bw) + 6 ?>" y="<?= $y + $tpBarH / 2 + 4 ?>"
                    font-family="Inter,sans-serif" font-size="10" font-weight="600" fill="#4b4136"><?= $qty ?></text>
            <?php $ti++; endforeach; ?>
          </svg>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- Orders: 7-day sales -->
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

<!-- ═══════════════════════════════════════════════════════
     BOOKINGS
     ═══════════════════════════════════════════════════════ -->

<!-- Bookings: Status column chart + Best-selling rent items -->
<section class="section">
  <div class="section-head">
    <span class="eyebrow">Bookings</span>
    <h2>Rental &amp; reservation insights</h2>
  </div>
  <div class="grid--pie">
    <!-- Bookings by status — column chart -->
    <div class="card chart-card">
      <div class="card__head">
        <div>
          <h2>Bookings by status</h2>
          <small class="micro">All-time reservation breakdown</small>
        </div>
      </div>
      <div class="card__body">
        <?php if (!$bookingStatuses): ?>
          <div class="muted" style="padding:20px 0">No bookings yet.</div>
        <?php else: ?>
          <?php
            $bsCount = count($bookingStatuses);
            $bsBarW = max(28, min(56, intval(400 / max(1, $bsCount))));
            $bsGap = max(12, intval($bsBarW * 0.4));
            $bsChartW = max(300, $bsCount * ($bsBarW + $bsGap) + $bsGap);
            $bsChartH = 160; $bsPadB = 28;
          ?>
          <div class="scroll-x">
            <svg class="chart-svg" viewBox="0 0 <?= $bsChartW ?> <?= $bsChartH + $bsPadB ?>"
                 role="img" aria-label="Column chart of bookings by status">
              <?php for ($gi = 0; $gi <= 3; $gi++):
                  $gy = 8 + ($bsChartH - 8) - ($gi / 3) * ($bsChartH - 8);
                  $gv = intval(($maxBookingStatus / 3) * $gi);
              ?>
                <line x1="<?= $bsGap / 2 ?>" y1="<?= $gy ?>" x2="<?= $bsChartW - $bsGap / 2 ?>" y2="<?= $gy ?>"
                      stroke="#e6dfd1" stroke-width="1" stroke-dasharray="<?= $gi === 0 ? '0' : '3,3' ?>"/>
                <text x="<?= $bsGap / 2 - 4 ?>" y="<?= $gy + 3 ?>" text-anchor="end"
                      font-family="Inter,sans-serif" font-size="9" fill="#8a7f70"><?= $gv ?></text>
              <?php endfor; ?>
              <?php $bsi = 0; foreach ($bookingStatuses as $label => $count):
                  $x = $bsGap + $bsi * ($bsBarW + $bsGap);
                  $bh = $maxBookingStatus > 0 ? ($count / $maxBookingStatus) * ($bsChartH - 8) : 0;
                  $color = $pieColors[$bsi % count($pieColors)];
              ?>
                <rect x="<?= $x ?>" y="<?= 8 + ($bsChartH - 8) - max(2, $bh) ?>" width="<?= $bsBarW ?>" height="<?= max(2, $bh) ?>"
                      fill="<?= $color ?>" rx="4" opacity=".9">
                  <title><?= e($label) ?>: <?= $count ?></title>
                </rect>
                <text x="<?= $x + $bsBarW / 2 ?>" y="<?= 8 + ($bsChartH - 8) - max(2, $bh) - 5 ?>" text-anchor="middle"
                      font-family="Inter,sans-serif" font-size="10" font-weight="600" fill="#4b4136"><?= $count ?></text>
                <text x="<?= $x + $bsBarW / 2 ?>" y="<?= $bsChartH + 16 ?>" text-anchor="middle"
                      font-family="Inter,sans-serif" font-size="9" fill="#8a7f70"><?= e($label) ?></text>
              <?php $bsi++; endforeach; ?>
            </svg>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Best-selling rent items -->
    <div class="card chart-card">
      <div class="card__head">
        <div>
          <h2>Best-selling rent items</h2>
          <small class="micro">Most booked rental items by quantity</small>
        </div>
      </div>
      <div class="card__body">
        <?php if (!$rentItemSales): ?>
          <div class="muted" style="padding:20px 0">No rental bookings yet.</div>
        <?php else: ?>
          <?php
            $riH = max(180, count($rentItemSales) * 28 + 20);
            $riPadL = 120; $riPadR = 50;
            $riBarH = 18; $riGap = 8;
            $riPlotW = 260;
          ?>
          <div class="scroll-x">
            <svg class="chart-svg" viewBox="0 0 <?= $riPadL + $riPlotW + $riPadR ?> <?= $riH ?>"
                 role="img" aria-label="Top best-selling rent items">
              <?php $rpi = 0; foreach ($rentItemSales as $name => $qty):
                  $y = 10 + $rpi * ($riBarH + $riGap);
                  $bw = $maxRentQty > 0 ? ($qty / $maxRentQty) * $riPlotW : 0;
                  $displayName = mb_strlen($name) > 16 ? mb_substr($name, 0, 14) . '…' : $name;
              ?>
                <text x="<?= $riPadL - 6 ?>" y="<?= $y + $riBarH / 2 + 4 ?>" text-anchor="end"
                      font-family="Inter,sans-serif" font-size="11" fill="#4b4136"><?= e($displayName) ?></text>
                <rect x="<?= $riPadL ?>" y="<?= $y ?>" width="<?= max(2, $bw) ?>" height="<?= $riBarH ?>"
                      fill="#2d6a4f" rx="4" opacity=".9">
                  <title><?= e($name) ?>: <?= $qty ?> booked</title>
                </rect>
                <text x="<?= $riPadL + max(2, $bw) + 6 ?>" y="<?= $y + $riBarH / 2 + 4 ?>"
                      font-family="Inter,sans-serif" font-size="10" font-weight="600" fill="#4b4136"><?= $qty ?></text>
              <?php $rpi++; endforeach; ?>
            </svg>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<!-- Bookings: Recent bookings -->
<section class="section">
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
</section>

<!-- Recent orders (at the end) -->
<section class="section">
  <div class="card">
    <div class="card__head">
      <div><h2>Recent orders</h2><small class="micro">Latest 8 orders</small></div>
      <a class="btn btn--ghost btn--sm" href="/admin/reports.php">All orders →</a>
    </div>
    <div class="table-wrap" id="recentOrdersWrap" style="border:0;border-radius:0;max-height:400px;overflow-y:auto;">
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
</section>

<script>
(function () {
  var wrap = document.getElementById('recentOrdersWrap');
  if (wrap) {
    setTimeout(function () { wrap.scrollTop = wrap.scrollHeight; }, 80);
  }
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
