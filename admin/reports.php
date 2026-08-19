<?php
/**
 * admin/reports.php — Sales report with calendar date picker.
 * Calendar replaces the old date-range inputs; all original charts and tables remain.
 */
use App\Models\Order;
use App\Models\Booking;

require_once __DIR__ . '/../init.php';
require_admin();

/* ---------- Calendar month + selected day ---------- */
$calMonth = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('n');
$calYear  = isset($_GET['year'])  ? (int) $_GET['year']  : (int) date('Y');
$selectedDay = $_GET['day'] ?? null;

if ($calMonth < 1)  { $calMonth = 12; $calYear--; }
if ($calMonth > 12) { $calMonth = 1;  $calYear++; }

$monthStart = mktime(0, 0, 0, $calMonth, 1, $calYear);
$daysInMonth = (int) date('t', $monthStart);
$firstDow    = (int) date('w', $monthStart);
$monthLabel  = date('F Y', $monthStart);

if ($selectedDay !== null) {
    $selTs = strtotime($selectedDay);
    if ($selTs === false || date('Y-m-d', $selTs) !== $selectedDay) {
        $selectedDay = null;
    }
}

/* ---------- Prev / next month ---------- */
$prevMonth = $calMonth - 1;
$prevYear  = $calYear;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
$nextMonth = $calMonth + 1;
$nextYear  = $calYear;
if ($nextMonth > 12) { $nextMonth = 1;  $nextYear++; }

/* ---------- Fetch month data ---------- */
$monthFrom = date('Y-m-d', $monthStart);
$monthTo   = date('Y-m-d', mktime(23, 59, 59, $calMonth, $daysInMonth, $calYear));

$allOrders   = Order::byDateRange($monthFrom, $monthTo);
$allBookings = Booking::byDateRange($monthFrom, $monthTo);

/* ---------- Per-day aggregates (for calendar pips) ---------- */
$dayData = [];
for ($d = 1; $d <= $daysInMonth; $d++) {
    $key = sprintf('%04d-%02d-%02d', $calYear, $calMonth, $d);
    $dayData[$key] = ['orders' => 0, 'bookings' => 0, 'orderRev' => 0.0, 'bookingRev' => 0.0];
}
foreach ($allOrders as $o) {
    $day = substr((string) ($o['created_at'] ?? ''), 0, 10);
    if (isset($dayData[$day])) {
        $dayData[$day]['orders']++;
        if ((string) ($o['payment_status'] ?? '') === 'paid') {
            $dayData[$day]['orderRev'] += (float) ($o['total'] ?? 0);
        }
    }
}
foreach ($allBookings as $b) {
    $day = substr((string) ($b['created_at'] ?? ''), 0, 10);
    if (isset($dayData[$day])) {
        $dayData[$day]['bookings']++;
        $dayData[$day]['bookingRev'] += (float) ($b['total'] ?? 0);
    }
}

/* ---------- Month totals ---------- */
$totalOrders   = count($allOrders);
$totalBookings = count($allBookings);
$totalOrderRev   = 0.0;
$totalBookingRev = 0.0;
foreach ($dayData as $dd) {
    $totalOrderRev   += $dd['orderRev'];
    $totalBookingRev += $dd['bookingRev'];
}

/* ---------- Selected day detail ---------- */
$dayOrders   = [];
$dayBookings = [];
$dayOrderTotal   = 0.0;
$dayBookingTotal = 0.0;
$selDayOrderCount   = 0;
$selDayBookingCount = 0;
if ($selectedDay !== null && isset($dayData[$selectedDay])) {
    foreach ($allOrders as $id => $o) {
        if (substr((string) ($o['created_at'] ?? ''), 0, 10) === $selectedDay) {
            $dayOrders[$id] = $o;
            $selDayOrderCount++;
            if ((string) ($o['payment_status'] ?? '') === 'paid') {
                $dayOrderTotal += (float) ($o['total'] ?? 0);
            }
        }
    }
    foreach ($allBookings as $id => $b) {
        if (substr((string) ($b['created_at'] ?? ''), 0, 10) === $selectedDay) {
            $dayBookings[$id] = $b;
            $selDayBookingCount++;
            $dayBookingTotal += (float) ($b['total'] ?? 0);
        }
    }
}

/* ---------- Charts: per-day totals across the full month ---------- */
$dayTotals = [];
$dayLabels = [];
$cursor = $monthStart;
$monthEndTs = mktime(23, 59, 59, $calMonth, $daysInMonth, $calYear);
while ($cursor <= $monthEndTs) {
    $key = date('Y-m-d', $cursor);
    $dayTotals[$key] = 0.0;
    $dayLabels[$key] = date('M j', $cursor);
    $cursor = strtotime('+1 day', $cursor);
}
foreach ($allOrders as $o) {
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
$cursor = $monthStart;
while ($cursor <= $monthEndTs) {
    $key = date('Y-m-d', $cursor);
    $dayOrderRevenue[$key]   = 0.0;
    $dayBookingRevenue[$key] = 0.0;
    $dayOrderCount[$key]     = 0;
    $dayBookingCount[$key]   = 0;
    $cursor = strtotime('+1 day', $cursor);
}
foreach ($allOrders as $o) {
    $day = substr((string) ($o['created_at'] ?? ''), 0, 10);
    if (isset($dayOrderCount[$day])) {
        $dayOrderCount[$day]++;
    }
    if ((string) ($o['payment_status'] ?? '') === 'paid' && isset($dayOrderRevenue[$day])) {
        $dayOrderRevenue[$day] += (float) ($o['total'] ?? 0);
    }
}
foreach ($allBookings as $b) {
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
$chartH   = 300;
$chartPad = 48;
$barW     = $dayCount <= 14 ? 46 : ($dayCount <= 30 ? 26 : 14);
$gap      = $dayCount <= 14 ? 22 : ($dayCount <= 30 ? 12 : 6);
$chartW   = max(360, $dayCount * ($barW + $gap) + $gap);

/* ---------- Breakdown KPIs ---------- */
$orderCount    = $totalOrders;
$paidCount     = 0;
$verifyingCount = 0;
$totalSales     = 0.0;
foreach ($allOrders as $o) {
    $ps = (string) ($o['payment_status'] ?? '');
    if ($ps === 'paid') {
        $paidCount++;
        $totalSales += (float) ($o['total'] ?? 0);
    } elseif ($ps === 'pending_verification') {
        $verifyingCount++;
    }
}

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
  .note-badge { display:inline-block; margin-top:6px; padding:3px 8px; border-radius:6px; font-size:11px; background:var(--gold-100,#fbf3e0); color:var(--gold-600,#9a6b00); border:1px dashed var(--gold,#b8860b); }
  .totals-row td { background:var(--surface-2); font-weight:700; color:var(--ink); border-top:2px solid var(--line); }
  .scroll-x { overflow-x:auto; }
  .grid--charts { display:grid; grid-template-columns:1fr 1fr; gap:22px; }
  @media (max-width:980px) { .grid--charts { grid-template-columns:1fr; } }
  .chart-legend { display:flex; gap:18px; margin-top:12px; flex-wrap:wrap; }
  .chart-legend__item { display:flex; align-items:center; gap:6px; font-size:14px; color:var(--muted); }
  .chart-legend__dot { width:10px; height:10px; border-radius:3px; flex-shrink:0; }
  .cal-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:2px; }
  .cal-head { text-align:center; font-size:11px; font-weight:600; color:var(--muted); text-transform:uppercase; letter-spacing:.06em; padding:8px 0; }
  .cal-day {
    min-height:72px; padding:6px 8px; border-radius:8px; border:1.5px solid transparent;
    background:var(--surface); cursor:pointer; transition:all .15s ease; position:relative;
  }
  .cal-day:hover { border-color:var(--gold); box-shadow:0 2px 8px rgba(0,0,0,.06); }
  .cal-day--empty { background:transparent; cursor:default; border:none; }
  .cal-day--empty:hover { box-shadow:none; border:none; }
  .cal-day--today { border-color:var(--gold); }
  .cal-day--selected { border-color:var(--gold); background:rgba(216,169,78,.08); box-shadow:0 2px 12px rgba(216,169,78,.18); }
  .cal-day--has-data { background:rgba(216,169,78,.04); }
  .cal-num { font-size:13px; font-weight:600; color:var(--ink); margin-bottom:4px; }
  .cal-day--today .cal-num { color:var(--gold); }
  .cal-pip { display:inline-flex; align-items:center; gap:3px; font-size:10px; line-height:1; padding:2px 5px; border-radius:4px; margin-top:2px; }
  .cal-pip--order { background:rgba(216,169,78,.15); color:#8a6b1e; }
  .cal-pip--booking { background:rgba(45,106,79,.12); color:#1b5e3b; }
  .cal-nav { display:flex; align-items:center; justify-content:space-between; gap:12px; }
  .cal-nav__title { font-family:var(--serif); font-size:1.3rem; font-weight:700; }
  .detail-card { border-left:3px solid var(--gold); }
  @media (max-width:600px) { .cal-day { min-height:56px; padding:4px; } .cal-pip { font-size:9px; padding:1px 3px; } }
</style>

<div class="page-head">
  <div class="page-head__row">
    <div>
      <span class="eyebrow">Insights</span>
      <h1 class="mt-2">Daily sales report</h1>
      <p>Click any day on the calendar to view that day's orders and bookings. Charts below show the full month.</p>
    </div>
  </div>
</div>

<!-- Breakdown KPIs -->
<div class="grid grid--stat mb-4">
  <div class="stat">
    <div class="stat__label">Orders this month</div>
    <div class="stat__value"><?= $orderCount ?></div>
  </div>
  <div class="stat">
    <div class="stat__label">Bookings this month</div>
    <div class="stat__value"><?= $totalBookings ?></div>
  </div>
  <div class="stat">
    <div class="stat__label">Order revenue</div>
    <div class="stat__value"><?= e(money($totalOrderRev)) ?></div>
  </div>
  <div class="stat">
    <div class="stat__label">Booking revenue</div>
    <div class="stat__value"><?= e(money($totalBookingRev)) ?></div>
  </div>
</div>

<!-- Calendar date picker -->
<div class="card mb-4">
  <div class="card__head">
    <div class="cal-nav" style="width:100%">
      <a class="btn btn--ghost btn--sm" href="/admin/reports.php?month=<?= $prevMonth ?>&year=<?= $prevYear ?>">&larr; <?= date('M', mktime(0,0,0,$prevMonth,1,$prevYear)) ?></a>
      <span class="cal-nav__title"><?= e($monthLabel) ?></span>
      <a class="btn btn--ghost btn--sm" href="/admin/reports.php?month=<?= $nextMonth ?>&year=<?= $nextYear ?>"><?= date('M', mktime(0,0,0,$nextMonth,1,$nextYear)) ?> &rarr;</a>
    </div>
  </div>
  <div class="card__body">
    <div class="cal-grid">
      <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dh): ?>
        <div class="cal-head"><?= $dh ?></div>
      <?php endforeach; ?>
      <?php for ($i = 0; $i < $firstDow; $i++): ?>
        <div class="cal-day cal-day--empty"></div>
      <?php endfor; ?>
      <?php $todayStr = date('Y-m-d'); for ($d = 1; $d <= $daysInMonth; $d++):
        $key  = sprintf('%04d-%02d-%02d', $calYear, $calMonth, $d);
        $dd   = $dayData[$key];
        $isToday    = ($key === $todayStr);
        $isSelected = ($selectedDay === $key);
        $hasData    = ($dd['orders'] > 0 || $dd['bookings'] > 0);
        $cls = 'cal-day';
        if ($isToday)    $cls .= ' cal-day--today';
        if ($isSelected) $cls .= ' cal-day--selected';
        if ($hasData)    $cls .= ' cal-day--has-data';
      ?>
        <a class="<?= $cls ?>" href="/admin/reports.php?month=<?= $calMonth ?>&year=<?= $calYear ?>&day=<?= $key ?>" style="text-decoration:none;color:inherit;">
          <div class="cal-num"><?= $d ?></div>
          <?php if ($dd['orders'] > 0): ?>
            <span class="cal-pip cal-pip--order"><?= $dd['orders'] ?> order<?= $dd['orders']===1?'':'s' ?></span>
          <?php endif; ?>
          <?php if ($dd['bookings'] > 0): ?>
            <span class="cal-pip cal-pip--booking"><?= $dd['bookings'] ?> booking<?= $dd['bookings']===1?'':'s' ?></span>
          <?php endif; ?>
        </a>
      <?php endfor; ?>
    </div>
  </div>
</div>

<!-- Selected day detail -->
<?php if ($selectedDay !== null): ?>
  <?php $selLabel = date('l, F j, Y', strtotime($selectedDay)); ?>

  <div class="grid grid--stat mb-4">
    <div class="stat">
      <div class="stat__label">Orders on <?= e(date('M j', strtotime($selectedDay))) ?></div>
      <div class="stat__value"><?= $selDayOrderCount ?></div>
    </div>
    <div class="stat">
      <div class="stat__label">Order revenue</div>
      <div class="stat__value"><?= e(money($dayOrderTotal)) ?></div>
    </div>
    <div class="stat">
      <div class="stat__label">Bookings</div>
      <div class="stat__value"><?= $selDayBookingCount ?></div>
    </div>
    <div class="stat">
      <div class="stat__label">Booking revenue</div>
      <div class="stat__value"><?= e(money($dayBookingTotal)) ?></div>
    </div>
  </div>

  <section class="section">
    <div class="card detail-card">
      <div class="card__head">
        <div><h2>Orders — <?= e($selLabel) ?></h2><small><?= $selDayOrderCount ?> order(s)</small></div>
      </div>
      <div class="table-wrap" style="border:0;border-radius:0;">
        <table class="tbl">
          <thead>
            <tr>
              <th>Date</th><th>Order ID</th><th>Customer</th><th>Items</th>
              <th class="num">Total</th><th>Payment</th><th>Status</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$dayOrders): ?>
            <tr><td colspan="7" class="muted t-center">No orders on this day.</td></tr>
          <?php else: foreach ($dayOrders as $id => $o):
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
              <td>
                <?= items_html($o['items'] ?? []) ?>
                <?php if (!empty($o['notes'])): ?>
                  <br><small class="note-badge"><?= e($o['notes']) ?></small>
                <?php endif; ?>
              </td>
              <td class="num"><?= e(money((float) ($o['total'] ?? 0))) ?></td>
              <td><span class="badge <?= e($pc) ?>"><?= e($pl) ?></span></td>
              <td><span class="badge <?= e($oc) ?>"><?= e($ol) ?></span></td>
            </tr>
          <?php endforeach; ?>
            <tr class="totals-row">
              <td colspan="4">Total paid sales</td>
              <td class="num"><?= e(money($dayOrderTotal)) ?></td>
              <td colspan="2"><?= $selDayOrderCount ?> order(s)</td>
            </tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <section class="section">
    <div class="card detail-card">
      <div class="card__head">
        <div><h2>Bookings — <?= e($selLabel) ?></h2><small><?= $selDayBookingCount ?> booking(s)</small></div>
      </div>
      <div class="table-wrap" style="border:0;border-radius:0;">
        <table class="tbl">
          <thead>
            <tr>
              <th>Date</th><th>Booking ID</th><th>Customer</th><th>Items</th>
              <th class="num">Total</th><th>Payment</th><th>Status</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$dayBookings): ?>
            <tr><td colspan="7" class="muted t-center">No bookings on this day.</td></tr>
          <?php else: foreach ($dayBookings as $id => $b):
              [$bl, $bc] = booking_status_label((string) ($b['status'] ?? ''));
              [$pl, $pc] = payment_status_label((string) ($b['payment_status'] ?? ''));
              $created = (string) ($b['created_at'] ?? '');
          ?>
            <tr>
              <td class="micro"><?= $created ? e(date('M j, Y g:i A', strtotime($created))) : '—' ?></td>
              <td><code style="font-size:12px;color:var(--ink-soft);"><?= e(substr((string) $id, 0, 10)) ?>…</code></td>
              <td>
                <strong><?= e($b['user_name'] ?? 'Guest') ?></strong><br>
                <small class="micro"><?= e($b['user_email'] ?? '—') ?></small>
              </td>
              <td>
                <?= items_html($b['items'] ?? []) ?>
                <?php if (!empty($b['notes'])): ?>
                  <br><small class="note-badge"><?= e($b['notes']) ?></small>
                <?php endif; ?>
              </td>
              <td class="num"><?= e(money((float) ($b['total'] ?? 0))) ?></td>
              <td><span class="badge <?= e($pc) ?>"><?= e($pl) ?></span></td>
              <td><span class="badge <?= e($bc) ?>"><?= e($bl) ?></span></td>
            </tr>
          <?php endforeach; ?>
            <tr class="totals-row">
              <td colspan="4">Total booking revenue</td>
              <td class="num"><?= e(money($dayBookingTotal)) ?></td>
              <td colspan="2"><?= $selDayBookingCount ?> booking(s)</td>
            </tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>
<?php endif; ?>

<!-- Daily sales bar chart -->
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
          <h3>No sales this month</h3>
          <p>Check back after orders come in.</p>
        </div>
      <?php else: ?>
        <div class="scroll-x">
          <svg class="chart-svg" viewBox="0 0 <?= $chartW ?> <?= $chartH + $chartPad ?>"
               role="img" aria-label="Bar chart of daily sales for <?= e($monthLabel) ?>">
            <defs>
              <linearGradient id="goldGrad2" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%"  stop-color="#D4A937"/>
                <stop offset="100%" stop-color="#B8934A"/>
              </linearGradient>
            </defs>
            <line x1="<?= $gap/2 ?>" y1="<?= $chartH ?>" x2="<?= $chartW - $gap/2 ?>" y2="<?= $chartH ?>"
                  stroke="rgba(255,255,255,0.08)" stroke-width="1"/>
            <?php $i = 0; foreach ($dayTotals as $day => $val):
                $h    = $val > 0 ? max(3, ($val / $maxDay) * ($chartH - 18)) : 2;
                $x    = $gap + $i * ($barW + $gap);
                $y    = $chartH - $h;
                $lab  = $dayLabels[$day];
                $showLabel = ($dayCount <= 14) || ($i % max(1, intval($dayCount/12)) === 0);
            ?>
              <rect x="<?= $x ?>" y="0" width="<?= $barW ?>" height="<?= $chartH ?>"
                    fill="#2D241B" rx="4" opacity=".4"/>
              <rect class="bar" x="<?= $x ?>" y="<?= $y ?>" width="<?= $barW ?>" height="<?= $h ?>"
                    fill="url(#goldGrad2)" rx="4">
                <title><?= e($lab . ' — ' . money($val)) ?></title>
              </rect>
              <?php if ($val > 0 && $dayCount <= 14): ?>
                <text x="<?= $x + $barW/2 ?>" y="<?= max(16, $y - 5) ?>" text-anchor="middle"
                      font-family="Inter, sans-serif" font-size="13" font-weight="600" fill="#F5F1E8">
                  <?= e('₱' . number_format($val, 0)) ?>
                </text>
              <?php endif; ?>
              <?php if ($showLabel): ?>
                <text x="<?= $x + $barW/2 ?>" y="<?= $chartH + 18 ?>" text-anchor="middle"
                      font-family="Inter, sans-serif" font-size="12" fill="#A8A29E">
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
          $linePadL = 60;
          $linePadB = 38;
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
                    stroke="rgba(255,255,255,0.08)" stroke-width="1" stroke-dasharray="<?= $gi === 0 ? '0' : '4,4' ?>"/>
              <text x="<?= $linePadL - 6 ?>" y="<?= $gy + 4 ?>" text-anchor="end"
                    font-family="Inter,sans-serif" font-size="12" fill="#A8A29E">₱<?= number_format($gv, 0) ?></text>
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
                      fill="none" stroke="#D4A937" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
            <?php foreach ($orderPts as $p): ?>
              <circle cx="<?= $p[0] ?>" cy="<?= $p[1] ?>" r="3.5" fill="#D4A937">
                <title><?= e(date('M j', strtotime($p[3])) . ' — Orders: ' . money($p[2])) ?></title>
              </circle>
            <?php endforeach; ?>

            <!-- Booking revenue line (green) -->
            <polyline points="<?= implode(' ', array_map(fn($p) => $p[0] . ',' . $p[1], $bookingPts)) ?>"
                      fill="none" stroke="#2E8B57" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
            <?php foreach ($bookingPts as $p): ?>
              <circle cx="<?= $p[0] ?>" cy="<?= $p[1] ?>" r="3.5" fill="#2E8B57">
                <title><?= e(date('M j', strtotime($p[3])) . ' — Bookings: ' . money($p[2])) ?></title>
              </circle>
            <?php endforeach; ?>

            <!-- X-axis labels -->
            <?php $n = count($days); foreach ($days as $i => $d):
                $x = $linePadL + ($n > 1 ? ($i / ($n - 1)) * $plotW : $plotW / 2);
                $showLabel = ($n <= 14) || ($i % max(1, intval($n / 12)) === 0);
            ?>
              <?php if ($showLabel): ?>
                <text x="<?= $x ?>" y="<?= $lineH + 18 ?>" text-anchor="middle"
                      font-family="Inter,sans-serif" font-size="12" fill="#A8A29E"><?= e($dayLabels[$d]) ?></text>
              <?php endif; ?>
            <?php endforeach; ?>
          </svg>
        </div>
        <div class="chart-legend">
          <span class="chart-legend__item"><span class="chart-legend__dot" style="background:#D4A937"></span> Order revenue</span>
          <span class="chart-legend__item"><span class="chart-legend__dot" style="background:#2E8B57"></span> Booking revenue</span>
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
          $colPadL = 50;
          $colPadB = 38;
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
                    stroke="rgba(255,255,255,0.08)" stroke-width="1" stroke-dasharray="<?= $gi === 0 ? '0' : '4,4' ?>"/>
              <text x="<?= $colPadL - 6 ?>" y="<?= $gy + 4 ?>" text-anchor="end"
                    font-family="Inter,sans-serif" font-size="12" fill="#A8A29E"><?= (int)$gv ?></text>
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
                    fill="#D4A937" rx="3" opacity=".9">
                <title><?= e(date('M j', strtotime($d)) . ' — Orders: ' . $dayOrderCount[$d]) ?></title>
              </rect>
              <!-- Booking bar (green) -->
              <rect x="<?= $bx + $halfW + 4 ?>" y="<?= 10 + $colPlotH - max(2, $bh) ?>" width="<?= $halfW ?>" height="<?= max(2, $bh) ?>"
                    fill="#2E8B57" rx="3" opacity=".9">
                <title><?= e(date('M j', strtotime($d)) . ' — Bookings: ' . $dayBookingCount[$d]) ?></title>
              </rect>
              <?php if ($showLabel): ?>
                <text x="<?= $centerX ?>" y="<?= $colH + 18 ?>" text-anchor="middle"
                      font-family="Inter,sans-serif" font-size="12" fill="#A8A29E"><?= e($dayLabels[$d]) ?></text>
              <?php endif; ?>
            <?php endforeach; ?>
          </svg>
        </div>
        <div class="chart-legend">
          <span class="chart-legend__item"><span class="chart-legend__dot" style="background:#D4A937"></span> Orders</span>
          <span class="chart-legend__item"><span class="chart-legend__dot" style="background:#2E8B57"></span> Bookings</span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Orders table -->
<section class="section">
  <div class="card">
    <div class="card__head">
      <div>
        <h2>Revenue vs Booking revenue</h2>
        <small class="micro">Order sales vs booking revenue per day</small>
      </div>
    </div>
    <div class="card__body">
      <?php
        $lineW = max(400, $dayCount * 60 + 60);
        $lineH = 300;
        $linePadL = 60;
        $linePadB = 38;
        $plotW = $lineW - $linePadL - 10;
        $plotH = $lineH - $linePadB - 10;
      ?>
      <div class="scroll-x">
        <svg class="chart-svg" viewBox="0 0 <?= $lineW ?> <?= $lineH + $linePadB ?>"
             role="img" aria-label="Line chart comparing order and booking revenue">
          <?php for ($gi = 0; $gi <= 4; $gi++):
              $gy = 10 + $plotH - ($gi / 4) * $plotH;
              $gv = ($maxRev / 4) * $gi;
          ?>
            <line x1="<?= $linePadL ?>" y1="<?= $gy ?>" x2="<?= $lineW - 10 ?>" y2="<?= $gy ?>"
                  stroke="#e6dfd1" stroke-width="1" stroke-dasharray="<?= $gi === 0 ? '0' : '4,4' ?>"/>
            <text x="<?= $linePadL - 6 ?>" y="<?= $gy + 4 ?>" text-anchor="end"
                  font-family="Inter,sans-serif" font-size="12" fill="#8a7f70">₱<?= number_format($gv, 0) ?></text>
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

          <polyline points="<?= implode(' ', array_map(fn($p) => $p[0] . ',' . $p[1], $orderPts)) ?>"
                    fill="none" stroke="#d8a94e" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
          <?php foreach ($orderPts as $p): ?>
            <circle cx="<?= $p[0] ?>" cy="<?= $p[1] ?>" r="3.5" fill="#d8a94e">
              <title><?= e(date('M j', strtotime($p[3])) . ' — Orders: ' . money($p[2])) ?></title>
            </circle>
          <?php endforeach; ?>

          <polyline points="<?= implode(' ', array_map(fn($p) => $p[0] . ',' . $p[1], $bookingPts)) ?>"
                    fill="none" stroke="#2d6a4f" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
          <?php foreach ($bookingPts as $p): ?>
            <circle cx="<?= $p[0] ?>" cy="<?= $p[1] ?>" r="3.5" fill="#2d6a4f">
              <title><?= e(date('M j', strtotime($p[3])) . ' — Bookings: ' . money($p[2])) ?></title>
            </circle>
          <?php endforeach; ?>

          <?php $n = count($days); foreach ($days as $i => $d):
              $x = $linePadL + ($n > 1 ? ($i / ($n - 1)) * $plotW : $plotW / 2);
              $showLabel = ($n <= 14) || ($i % max(1, intval($n / 12)) === 0);
          ?>
            <?php if ($showLabel): ?>
              <text x="<?= $x ?>" y="<?= $lineH + 18 ?>" text-anchor="middle"
                    font-family="Inter,sans-serif" font-size="12" fill="#8a7f70"><?= e($dayLabels[$d]) ?></text>
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
</section>

<!-- Order vs Booking count -->
<section class="section">
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
        $colH = 300;
        $colPadL = 50;
        $colPadB = 38;
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
                  font-family="Inter,sans-serif" font-size="12" fill="#8a7f70"><?= (int)$gv ?></text>
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
            <rect x="<?= $bx ?>" y="<?= 10 + $colPlotH - max(2, $oh) ?>" width="<?= $halfW ?>" height="<?= max(2, $oh) ?>"
                  fill="#d8a94e" rx="3" opacity=".9">
              <title><?= e(date('M j', strtotime($d)) . ' — Orders: ' . $dayOrderCount[$d]) ?></title>
            </rect>
            <rect x="<?= $bx + $halfW + 4 ?>" y="<?= 10 + $colPlotH - max(2, $bh) ?>" width="<?= $halfW ?>" height="<?= max(2, $bh) ?>"
                  fill="#2d6a4f" rx="3" opacity=".9">
              <title><?= e(date('M j', strtotime($d)) . ' — Bookings: ' . $dayBookingCount[$d]) ?></title>
            </rect>
            <?php if ($showLabel): ?>
              <text x="<?= $centerX ?>" y="<?= $colH + 18 ?>" text-anchor="middle"
                    font-family="Inter,sans-serif" font-size="12" fill="#8a7f70"><?= e($dayLabels[$d]) ?></text>
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
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
