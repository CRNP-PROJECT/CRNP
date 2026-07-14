<?php
/**
 * admin/reports.php — Daily sales report with date filter + breakdown + chart.
 */
require_once __DIR__ . '/../init.php';
require_admin();

$db = getDB();

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
$orders = rows($db->retrieve('/orders'));
$inRange = [];
foreach ($orders as $id => $o) {
    if (!is_array($o)) continue;
    $created = (string) ($o['created_at'] ?? '');
    $day = substr($created, 0, 10);
    if ($day >= $from && $day <= $to) {
        $inRange[$id] = $o;
    }
}
uasort($inRange, function ($a, $b) {
    $ta = strtotime((string) ($a['created_at'] ?? '')) ?: 0;
    $tb = strtotime((string) ($b['created_at'] ?? '')) ?: 0;
    return $ta <=> $tb; // ascending by date
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
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
