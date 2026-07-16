<?php
/**
 * admin/reports.php — Daily sales report with date filter + breakdown + chart.
 */
use App\Models\Order;
use App\Models\Booking;

require_once __DIR__ . '/../init.php';
require_admin();

/* ---------- Date range (GET) ---------- */
$today = date('Y-m-d');
$from  = isset($_GET['from']) ? (string) $_GET['from'] : date('Y-m-d', strtotime('-6 days'));
$to    = isset($_GET['to'])   ? (string) $_GET['to']   : $today;

// Normalize & validate
$fromTs = strtotime($from);
$toTs   = strtotime($to);
if ($fromTs === false) { $from = date('Y-m-d', strtotime('-6 days')); $fromTs = strtotime($from); }
if ($toTs   === false) { $to   = $today; $toTs = strtotime($to); }
if ($fromTs > $toTs)   { [$from, $to] = [$to, $from]; [$fromTs, $toTs] = [$toTs, $fromTs]; }
// Cap range at 90 days to keep chart legible
if (($toTs - $fromTs) / 86400 > 90) {
    $from = date('Y-m-d', $toTs - 90 * 86400);
    $fromTs = strtotime($from);
}

/* ---------- Filter orders in range ---------- */
$inRange = Order::byDateRange($from, $to);
uasort($inRange, function ($a, $b) {
    $ta = strtotime((string) ($a['created_at'] ?? '')) ?: 0;
    $tb = strtotime((string) ($b['created_at'] ?? '')) ?: 0;
    return $ta <=> $tb;
});

/* ---------- Filter bookings in range ---------- */
$bookingsInRange = Booking::byDateRange($from, $to);
uasort($bookingsInRange, function ($a, $b) {
    $ta = strtotime((string) ($a['created_at'] ?? '')) ?: 0;
    $tb = strtotime((string) ($b['created_at'] ?? '')) ?: 0;
    return $ta <=> $tb;
});

/* ---------- Breakdown ---------- */
$orderCount    = count($inRange);
$paidCount     = 0;
$verifyingCount = 0;
$totalSales     = 0.0;
foreach ($inRange as $o) {
    $ps = (string) ($o['payment_status'] ?? '');
    if ($ps === 'paid') {
        $paidCount++;
        $totalSales += (float) ($o['total'] ?? 0);
    } elseif ($ps === 'pending_verification') {
        $verifyingCount++;
    }
}

/* ---------- Per-day totals for chart ---------- */
$dayTotals = [];
$dayLabels = [];
$cursor = $fromTs;
while ($cursor <= $toTs) {
    $key = date('Y-m-d', $cursor);
    $dayTotals[$key] = 0.0;
    $dayLabels[$key] = date('M j', $cursor);
    $cursor = strtotime('+1 day', $cursor);
}
foreach ($inRange as $o) {
    if ((string) ($o['payment_status'] ?? '') !== 'paid') continue;
    $day = substr((string) ($o['created_at'] ?? ''), 0, 10);
    if (isset($dayTotals[$day])) {
        $dayTotals[$day] += (float) ($o['total'] ?? 0);
    }
}
$maxDay = max($dayTotals) ?: 1.0;

/* ---------- Per-day order vs booking revenue ---------- */
$dayOrderRevenue   = [];
$dayBookingRevenue = [];
$dayOrderCount     = [];
$dayBookingCount   = [];
$cursor = $fromTs;
while ($cursor <= $toTs) {
    $key = date('Y-m-d', $cursor);
    $dayOrderRevenue[$key]   = 0.0;
    $dayBookingRevenue[$key] = 0.0;
    $dayOrderCount[$key]     = 0;
    $dayBookingCount[$key]   = 0;
    $cursor = strtotime('+1 day', $cursor);
}
foreach ($inRange as $o) {
    $day = substr((string) ($o['created_at'] ?? ''), 0, 10);
    if (isset($dayOrderCount[$day])) {
        $dayOrderCount[$day]++;
    }
    if ((string) ($o['payment_status'] ?? '') === 'paid' && isset($dayOrderRevenue[$day])) {
        $dayOrderRevenue[$day] += (float) ($o['total'] ?? 0);
    }
}
foreach ($bookingsInRange as $b) {
    $day = substr((string) ($b['created_at'] ?? ''), 0, 10);
    if (isset($dayBookingCount[$day])) {
        $dayBookingCount[$day]++;
    }
    if (isset($dayBookingRevenue[$day])) {
        $dayBookingRevenue[$day] += (float) ($b['total'] ?? 0);
    }
}
$maxOrderRev   = max($dayOrderRevenue) ?: 1.0;
$maxBookingRev = max($dayBookingRevenue) ?: 1.0;
$maxRev        = max($maxOrderRev, $maxBookingRev);
$maxOrderCnt   = max($dayOrderCount) ?: 1;
$maxBookingCnt = max($dayBookingCount) ?: 1;
$maxCnt        = max($maxOrderCnt, $maxBookingCnt);

/* ---------- Chart geometry ---------- */
$dayCount = count($dayTotals);
$chartH   = 200;
$chartPad = 36;
$barW     = $dayCount <= 14 ? 46 : ($dayCount <= 30 ? 26 : 14);
$gap      = $dayCount <= 14 ? 22 : ($dayCount <= 30 ? 12 : 6);
$chartW   = max(360, $dayCount * ($barW + $gap) + $gap);

$pageTitle = 'Reports';
$activeNav = 'reports';
$layout    = 'wide';
require_once __DIR__ . '/../includes/header.php';
?>
<style>
  .micro { font-size:12px; color:var(--muted); }
  .stat__hint { font-size:12px; color:var(--muted); margin-top:4px; position:relative; z-index:1; }
  .filter-bar { display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end; }
  .filter-bar .field { min-width:140px; }
  .chart-svg { width:100%; height:auto; display:block; }
  .chart-svg .bar { transition:opacity .15s ease; cursor:pointer; }
  .chart-svg .bar:hover { opacity:.82; }
  .totals-row td { background:var(--surface-2); font-weight:700; color:var(--ink); border-top:2px solid var(--line); }
  .scroll-x { overflow-x:auto; }
  .grid--charts { display:grid; grid-template-columns:1fr 1fr; gap:22px; }
  @media (max-width:980px) { .grid--charts { grid-template-columns:1fr; } }
  .chart-legend { display:flex; gap:18px; margin-top:12px; flex-wrap:wrap; }
  .chart-legend__item { display:flex; align-items:center; gap:6px; font-size:12px; color:var(--muted); }
  .chart-legend__dot { width:10px; height:10px; border-radius:3px; flex-shrink:0; }
</style>

<div class="page-head">
  <div class="page-head__row">
    <div>
      <span class="eyebrow">Insights</span>
      <h1 class="mt-2">Daily sales report</h1>
      <p>Track paid order volume over time. Filter by date range and review the breakdown.</p>
    </div>
  </div>
</div>

<!-- Date filter -->
<div class="card mb-4">
  <div class="card__body">
    <form method="get" action="/admin/reports.php" class="filter-bar">
      <div class="field">
        <label for="from">From</label>
        <input class="input" type="date" id="from" name="from" value="<?= e($from) ?>" max="<?= e($today) ?>">
      </div>
      <div class="field">
        <label for="to">To</label>
        <input class="input" type="date" id="to" name="to" value="<?= e($to) ?>" max="<?= e($today) ?>">
      </div>
      <div class="row">
        <button class="btn btn--gold" type="submit">Apply filter</button>
        <a class="btn btn--ghost" href="/admin/reports.php">Reset</a>
      </div>
      <div class="grow"></div>
      <small class="micro" style="align-self:flex-end;padding-bottom:12px;">
        Showing <?= e($from) ?> → <?= e($to) ?> (<?= $dayCount ?> day(s))
      </small>
    </form>
  </div>
</div>

<!-- Breakdown KPIs -->
<div class="grid grid--stat mb-4">
  <div class="stat">
    <div class="stat__label">Orders in range</div>
    <div class="stat__value"><?= $orderCount ?></div>
  </div>
  <div class="stat">
    <div class="stat__label">Paid orders</div>
    <div class="stat__value"><?= $paidCount ?></div>
  </div>
  <div class="stat">
    <div class="stat__label">Verifying</div>
    <div class="stat__value"><?= $verifyingCount ?></div>
  </div>
  <div class="stat">
    <div class="stat__label">Total sales</div>
    <div class="stat__value"><?= e(money($totalSales)) ?></div>
  </div>
</div>

<!-- Chart -->
<section class="section">
  <div class="card">
    <div class="card__head">
      <div>
        <h2>Daily sales</h2>
        <small class="micro">Sum of paid order totals per day</small>
      </div>
    </div>
    <div class="card__body">
      <?php if ($dayCount === 0 || $totalSales == 0): ?>
        <div class="empty">
          <div class="empty__icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18M7 14l3-3 4 4 5-6"/></svg>
          </div>
          <h3>No sales in this range</h3>
          <p>Try widening the date filter or check back after orders come in.</p>
        </div>
      <?php else: ?>
        <div class="scroll-x">
          <svg class="chart-svg" viewBox="0 0 <?= $chartW ?> <?= $chartH + $chartPad ?>"
               role="img" aria-label="Bar chart of daily sales from <?= e($from) ?> to <?= e($to) ?>">
            <defs>
              <linearGradient id="goldGrad2" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%"  stop-color="#d8a94e"/>
                <stop offset="100%" stop-color="#a9751f"/>
              </linearGradient>
            </defs>
            <line x1="<?= $gap/2 ?>" y1="<?= $chartH ?>" x2="<?= $chartW - $gap/2 ?>" y2="<?= $chartH ?>"
                  stroke="#e6dfd1" stroke-width="1"/>
            <?php $i = 0; foreach ($dayTotals as $day => $val):
                $h    = $val > 0 ? max(3, ($val / $maxDay) * ($chartH - 18)) : 2;
                $x    = $gap + $i * ($barW + $gap);
                $y    = $chartH - $h;
                $lab  = $dayLabels[$day];
                $showLabel = ($dayCount <= 14) || ($i % max(1, intval($dayCount/12)) === 0);
            ?>
              <rect x="<?= $x ?>" y="0" width="<?= $barW ?>" height="<?= $chartH ?>"
                    fill="#efe8da" rx="4" opacity=".4"/>
              <rect class="bar" x="<?= $x ?>" y="<?= $y ?>" width="<?= $barW ?>" height="<?= $h ?>"
                    fill="url(#goldGrad2)" rx="4">
                <title><?= e($lab . ' — ' . money($val)) ?></title>
              </rect>
              <?php if ($val > 0 && $dayCount <= 14): ?>
                <text x="<?= $x + $barW/2 ?>" y="<?= max(12, $y - 5) ?>" text-anchor="middle"
                      font-family="Inter, sans-serif" font-size="10" font-weight="600" fill="#4b4136">
                  <?= e('₱' . number_format($val, 0)) ?>
                </text>
              <?php endif; ?>
              <?php if ($showLabel): ?>
                <text x="<?= $x + $barW/2 ?>" y="<?= $chartH + 16 ?>" text-anchor="middle"
                      font-family="Inter, sans-serif" font-size="10" fill="#8a7f70">
                  <?= e($lab) ?>
                </text>
              <?php endif; ?>
            <?php $i++; endforeach; ?>
          </svg>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- Order vs Booking comparison charts -->
<section class="section">
  <div class="grid--charts">
    <!-- Line chart: Revenue comparison -->
    <div class="card">
      <div class="card__head">
        <div>
          <h2>Revenue comparison</h2>
          <small class="micro">Order sales vs booking revenue per day</small>
        </div>
      </div>
      <div class="card__body">
        <?php
          $lineW = max(400, $dayCount * 60 + 60);
          $lineH = 180;
          $linePadL = 50;
          $linePadB = 28;
          $plotW = $lineW - $linePadL - 10;
          $plotH = $lineH - $linePadB - 10;
        ?>
        <div class="scroll-x">
          <svg class="chart-svg" viewBox="0 0 <?= $lineW ?> <?= $lineH + $linePadB ?>"
               role="img" aria-label="Line chart comparing order and booking revenue">
            <!-- grid lines -->
            <?php for ($gi = 0; $gi <= 4; $gi++):
                $gy = 10 + $plotH - ($gi / 4) * $plotH;
                $gv = ($maxRev / 4) * $gi;
            ?>
              <line x1="<?= $linePadL ?>" y1="<?= $gy ?>" x2="<?= $lineW - 10 ?>" y2="<?= $gy ?>"
                    stroke="#e6dfd1" stroke-width="1" stroke-dasharray="<?= $gi === 0 ? '0' : '4,4' ?>"/>
              <text x="<?= $linePadL - 6 ?>" y="<?= $gy + 4 ?>" text-anchor="end"
                    font-family="Inter,sans-serif" font-size="9" fill="#8a7f70">₱<?= number_format($gv, 0) ?></text>
            <?php endfor; ?>

            <?php
              $days = array_keys($dayOrderRevenue);
              $coords = function(array $data, float $max) use ($days, $linePadL, $plotW, $plotH) {
                  $pts = [];
                  $n = count($days);
                  foreach ($days as $i => $d) {
                      $x = $linePadL + ($n > 1 ? ($i / ($n - 1)) * $plotW : $plotW / 2);
                      $y = 10 + $plotH - ($max > 0 ? ($data[$d] / $max) * $plotH : 0);
                      $pts[] = [$x, $y, $data[$d], $d];
                  }
                  return $pts;
              };
              $orderPts   = $coords($dayOrderRevenue, $maxRev);
              $bookingPts = $coords($dayBookingRevenue, $maxRev);
            ?>

            <!-- Order revenue line (gold) -->
            <polyline points="<?= implode(' ', array_map(fn($p) => $p[0] . ',' . $p[1], $orderPts)) ?>"
                      fill="none" stroke="#d8a94e" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
            <?php foreach ($orderPts as $p): ?>
              <circle cx="<?= $p[0] ?>" cy="<?= $p[1] ?>" r="3.5" fill="#d8a94e">
                <title><?= e(date('M j', strtotime($p[3])) . ' — Orders: ' . money($p[2])) ?></title>
              </circle>
            <?php endforeach; ?>

            <!-- Booking revenue line (green) -->
            <polyline points="<?= implode(' ', array_map(fn($p) => $p[0] . ',' . $p[1], $bookingPts)) ?>"
                      fill="none" stroke="#2d6a4f" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
            <?php foreach ($bookingPts as $p): ?>
              <circle cx="<?= $p[0] ?>" cy="<?= $p[1] ?>" r="3.5" fill="#2d6a4f">
                <title><?= e(date('M j', strtotime($p[3])) . ' — Bookings: ' . money($p[2])) ?></title>
              </circle>
            <?php endforeach; ?>

            <!-- X-axis labels -->
            <?php $n = count($days); foreach ($days as $i => $d):
                $x = $linePadL + ($n > 1 ? ($i / ($n - 1)) * $plotW : $plotW / 2);
                $showLabel = ($n <= 14) || ($i % max(1, intval($n / 12)) === 0);
            ?>
              <?php if ($showLabel): ?>
                <text x="<?= $x ?>" y="<?= $lineH + 16 ?>" text-anchor="middle"
                      font-family="Inter,sans-serif" font-size="9" fill="#8a7f70"><?= e($dayLabels[$d]) ?></text>
              <?php endif; ?>
            <?php endforeach; ?>
          </svg>
        </div>
        <div class="chart-legend">
          <span class="chart-legend__item"><span class="chart-legend__dot" style="background:#d8a94e"></span> Order revenue</span>
          <span class="chart-legend__item"><span class="chart-legend__dot" style="background:#2d6a4f"></span> Booking revenue</span>
        </div>
      </div>
    </div>

    <!-- Column chart: Count comparison -->
    <div class="card">
      <div class="card__head">
        <div>
          <h2>Order vs Booking count</h2>
          <small class="micro">Number of orders and bookings per day</small>
        </div>
      </div>
      <div class="card__body">
        <?php
          $colW = max(400, $dayCount * 60 + 60);
          $colH = 180;
          $colPadL = 40;
          $colPadB = 28;
          $colPlotW = $colW - $colPadL - 10;
          $colPlotH = $colH - $colPadB - 10;
          $groupW = max(20, min(50, $colPlotW / max(1, $dayCount) * 0.7));
          $barPairW = $groupW * 2 + 4;
        ?>
        <div class="scroll-x">
          <svg class="chart-svg" viewBox="0 0 <?= $colW ?> <?= $colH + $colPadB ?>"
               role="img" aria-label="Column chart comparing order and booking counts">
            <?php for ($gi = 0; $gi <= 4; $gi++):
                $gy = 10 + $colPlotH - ($gi / 4) * $colPlotH;
                $gv = ($maxCnt / 4) * $gi;
            ?>
              <line x1="<?= $colPadL ?>" y1="<?= $gy ?>" x2="<?= $colW - 10 ?>" y2="<?= $gy ?>"
                    stroke="#e6dfd1" stroke-width="1" stroke-dasharray="<?= $gi === 0 ? '0' : '4,4' ?>"/>
              <text x="<?= $colPadL - 6 ?>" y="<?= $gy + 4 ?>" text-anchor="end"
                    font-family="Inter,sans-serif" font-size="9" fill="#8a7f70"><?= (int)$gv ?></text>
            <?php endfor; ?>

            <?php $days = array_keys($dayOrderCount); $n = count($days);
                  $totalGroupSpace = $colPlotW / max(1, $n);
                  $actualGroupW = min($barPairW + 8, $totalGroupSpace * 0.85);
            ?>
            <?php foreach ($days as $i => $d):
                $centerX = $colPadL + $totalGroupSpace * $i + $totalGroupSpace / 2;
                $ox = $centerX - $actualGroupW / 2;
                $bx = $ox;
                $halfW = $actualGroupW / 2 - 2;
                $oh = $maxCnt > 0 ? ($dayOrderCount[$d] / $maxCnt) * $colPlotH : 0;
                $bh = $maxCnt > 0 ? ($dayBookingCount[$d] / $maxCnt) * $colPlotH : 0;
                $showLabel = ($n <= 14) || ($i % max(1, intval($n / 12)) === 0);
            ?>
              <!-- Order bar (gold) -->
              <rect x="<?= $bx ?>" y="<?= 10 + $colPlotH - max(2, $oh) ?>" width="<?= $halfW ?>" height="<?= max(2, $oh) ?>"
                    fill="#d8a94e" rx="3" opacity=".9">
                <title><?= e(date('M j', strtotime($d)) . ' — Orders: ' . $dayOrderCount[$d]) ?></title>
              </rect>
              <!-- Booking bar (green) -->
              <rect x="<?= $bx + $halfW + 4 ?>" y="<?= 10 + $colPlotH - max(2, $bh) ?>" width="<?= $halfW ?>" height="<?= max(2, $bh) ?>"
                    fill="#2d6a4f" rx="3" opacity=".9">
                <title><?= e(date('M j', strtotime($d)) . ' — Bookings: ' . $dayBookingCount[$d]) ?></title>
              </rect>
              <?php if ($showLabel): ?>
                <text x="<?= $centerX ?>" y="<?= $colH + 16 ?>" text-anchor="middle"
                      font-family="Inter,sans-serif" font-size="9" fill="#8a7f70"><?= e($dayLabels[$d]) ?></text>
              <?php endif; ?>
            <?php endforeach; ?>
          </svg>
        </div>
        <div class="chart-legend">
          <span class="chart-legend__item"><span class="chart-legend__dot" style="background:#d8a94e"></span> Orders</span>
          <span class="chart-legend__item"><span class="chart-legend__dot" style="background:#2d6a4f"></span> Bookings</span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Orders table -->
<section class="section">
  <div class="card">
    <div class="card__head">
      <div><h2>Orders in range</h2><small><?= $orderCount ?> order(s)</small></div>
    </div>
    <div class="table-wrap" style="border:0;border-radius:0;">
      <table class="tbl">
        <thead>
          <tr>
            <th>Date</th><th>Order ID</th><th>Customer</th>
            <th class="num">Total</th><th>Payment</th><th>Order status</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$inRange): ?>
          <tr><td colspan="6" class="muted t-center">No orders in this date range.</td></tr>
        <?php else: foreach ($inRange as $id => $o):
            [$pl, $pc] = payment_status_label((string) ($o['payment_status'] ?? ''));
            [$ol, $oc] = order_status_label((string) ($o['status'] ?? ''));
            $created = (string) ($o['created_at'] ?? '');
        ?>
          <tr>
            <td class="micro"><?= $created ? e(date('M j, Y g:i A', strtotime($created))) : '—' ?></td>
            <td><code style="font-size:12px;color:var(--ink-soft);"><?= e(substr((string) $id, 0, 10)) ?>…</code></td>
            <td>
              <strong><?= e($o['user_name'] ?? 'Guest') ?></strong><br>
              <small class="micro"><?= e($o['user_email'] ?? '—') ?></small>
            </td>
            <td class="num"><?= e(money((float) ($o['total'] ?? 0))) ?></td>
            <td><span class="badge <?= e($pc) ?>"><?= e($pl) ?></span></td>
            <td><span class="badge <?= e($oc) ?>"><?= e($ol) ?></span></td>
          </tr>
        <?php endforeach; ?>
          <tr class="totals-row">
            <td colspan="3">Total paid sales</td>
            <td class="num"><?= e(money($totalSales)) ?></td>
            <td colspan="2"><?= $paidCount ?> paid of <?= $orderCount ?> order(s)</td>
          </tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>

<!-- Bookings in range -->
<section class="section">
  <div class="card">
    <div class="card__head">
      <div><h2>Bookings in range</h2><small><?= count($bookingsInRange) ?> booking(s)</small></div>
    </div>
    <div class="table-wrap" style="border:0;border-radius:0;">
      <table class="tbl">
        <thead>
          <tr>
            <th>Date</th><th>Booking ID</th><th>Customer</th>
            <th class="num">Total</th><th>Payment</th><th>Status</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$bookingsInRange): ?>
          <tr><td colspan="6" class="muted t-center">No bookings in this date range.</td></tr>
        <?php else: $bookingTotalSales = 0.0; foreach ($bookingsInRange as $bid => $b):
            [$bl, $bc] = booking_status_label((string) ($b['status'] ?? ''));
            [$pl, $pc] = payment_status_label((string) ($b['payment_status'] ?? ''));
            $created = (string) ($b['created_at'] ?? '');
            $bTotal = (float) ($b['total'] ?? 0);
            if ((string) ($b['payment_status'] ?? '') === 'paid') $bookingTotalSales += $bTotal;
        ?>
          <tr>
            <td class="micro"><?= $created ? e(date('M j, Y g:i A', strtotime($created))) : '—' ?></td>
            <td><code style="font-size:12px;color:var(--ink-soft);"><?= e(substr((string) $bid, 0, 10)) ?>…</code></td>
            <td>
              <strong><?= e($b['user_name'] ?? 'Guest') ?></strong><br>
              <small class="micro"><?= e($b['user_email'] ?? '—') ?></small>
            </td>
            <td class="num"><?= e(money($bTotal)) ?></td>
            <td><span class="badge <?= e($pc) ?>"><?= e($pl) ?></span></td>
            <td><span class="badge <?= e($bc) ?>"><?= e($bl) ?></span></td>
          </tr>
        <?php endforeach; ?>
          <tr class="totals-row">
            <td colspan="3">Total booking revenue</td>
            <td class="num"><?= e(money($bookingTotalSales)) ?></td>
            <td colspan="2"><?= count($bookingsInRange) ?> booking(s)</td>
          </tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
