<?php
/**
 * kitchen/index.php — Kitchen Display: active orders + status workflow.
 *
 * Status chain (forward only): pending → accepted → preparing → ready → done
 *   accept (pending→accepted)  | start (accepted→preparing)
 *   ready (preparing→ready)    | done  (ready→done)
 */
require_once __DIR__ . '/../init.php';
require_kitchen();

use App\Models\Order;

/* ---------- small display helpers (kept local to kitchen pages) ---------- */
if (!function_exists('k_short_id')) {
    function k_short_id(string $id): string {
        return substr($id, 0, 8);
    }
}
if (!function_exists('k_customer_name')) {
    function k_customer_name(array $order): string {
        $n = $order['customer_name'] ?? $order['user_name'] ?? $order['name'] ?? '';
        return trim((string)$n) !== '' ? (string)$n : 'Guest';
    }
}
if (!function_exists('k_items_html')) {
    function k_items_html($items): string {
        if (!is_array($items) || empty($items)) {
            return '<span class="muted">No items</span>';
        }
        $parts = [];
        foreach ($items as $it) {
            if (!is_array($it)) continue;
            $name = (string)($it['name'] ?? 'Item');
            $qty  = (int)($it['qty'] ?? $it['quantity'] ?? 1);
            $parts[] = e($name) . ' <span class="muted">&times;' . $qty . '</span>';
        }
        return $parts ? implode(', ', $parts) : '<span class="muted">No items</span>';
    }
}
if (!function_exists('k_elapsed')) {
    function k_elapsed(?string $ts): string {
        if ($ts === null || $ts === '') return '—';
        $t = strtotime($ts);
        if ($t === false) return '—';
        $diff = max(0, time() - $t);
        if ($diff < 60)   return 'just now';
        if ($diff < 3600) return (int)floor($diff / 60) . 'm ago';
        if ($diff < 86400) return (int)floor($diff / 3600) . 'h ago';
        return (int)floor($diff / 86400) . 'd ago';
    }
}

/* ---------- POST: status transition (skip the full list fetch) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action  = (string)post('action', '');
    $orderId = (string)post('order_id', '');

    // action => [ currentStatus => newStatus ]
    $transitions = [
        'accept' => ['pending'   => 'accepted'],
        'start'  => ['accepted'  => 'preparing'],
        'ready'  => ['preparing' => 'ready'],
        'done'   => ['ready'     => 'done'],
    ];

    if ($orderId === '' || !isset($transitions[$action])) {
        flash('Invalid request.', 'danger');
        redirect('/kitchen/');
    }

    $order = Order::find($orderId);
    if (!$order || !isset($order->status)) {
        flash('Order not found.', 'danger');
        redirect('/kitchen/');
    }

    $current = (string)$order->status;
    $map     = $transitions[$action];
    if (!isset($map[$current])) {
        flash('Invalid status transition.', 'danger');
        redirect('/kitchen/');
    }

    $newStatus = $map[$current];
    $patch = ['status' => $newStatus, 'updated_at' => now()];
    if ($newStatus === 'accepted')  $patch['accepted_at']  = now();
    if ($newStatus === 'preparing') $patch['preparing_at'] = now();
    if ($newStatus === 'ready')     $patch['ready_at']     = now();
    if ($newStatus === 'done') {
        $patch['done_at'] = now();
        $patch['done_by'] = $_SESSION['kitchen_name'] ?? '';
    }

    try {
        $order->update($patch);
        flash('Order #' . k_short_id($orderId) . ' moved to ' . ucfirst($newStatus) . '.', 'ok');
    } catch (Throwable $ex) {
        flash('Could not update the order. Please try again.', 'danger');
    }
    redirect('/kitchen/');
}

/* ---------- GET: list + filter ---------- */
$allOrders = Order::raw();
$statusFilter = isset($_GET['status']) ? (string)$_GET['status'] : 'active';
$validFilters = ['all', 'active', 'accepted', 'preparing', 'ready', 'done'];
if (!in_array($statusFilter, $validFilters, true)) {
    $statusFilter = 'active';
}
$activeStatuses = ['accepted', 'preparing', 'ready'];

// Sort: newest created_at first (missing dates sink to the bottom).
$sorted = $allOrders;
uasort($sorted, function ($a, $b) {
    $ta = strtotime((string)($a['created_at'] ?? ''));
    $tb = strtotime((string)($b['created_at'] ?? ''));
    if ($ta === false && $tb === false) return 0;
    if ($ta === false) return 1;
    if ($tb === false) return -1;
    return $tb - $ta;
});

// Apply filter
$orders = [];
foreach ($sorted as $id => $o) {
    if (!is_array($o)) continue;
    $s = (string)($o['status'] ?? 'pending');
    if ($statusFilter === 'all') {
        $orders[$id] = $o;
    } elseif ($statusFilter === 'active') {
        if (in_array($s, $activeStatuses, true)) $orders[$id] = $o;
    } else {
        if ($s === $statusFilter) $orders[$id] = $o;
    }
}

// Stat strip counts (across ALL orders, ignoring the filter)
$statCounts = ['preparing' => 0, 'ready' => 0];
foreach ($allOrders as $o) {
    if (!is_array($o)) continue;
    $s = (string)($o['status'] ?? '');
    if (isset($statCounts[$s])) $statCounts[$s]++;
}

$filterPills = [
    'active'    => 'Active',
    'accepted'  => 'Accepted',
    'preparing' => 'Preparing',
    'ready'     => 'Ready',
    'done'      => 'Done',
    'all'       => 'All',
];

$pageTitle = 'Kitchen Display';
$activeNav = 'orders';
$layout    = 'wide';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
  <div class="page-head__row">
    <div>
      <span class="eyebrow">Kitchen Display</span>
      <h1 class="mt-2">Active Tickets</h1>
      <p>Move each order along the line: accepted &rarr; preparing &rarr; ready &rarr; done.</p>
    </div>
  </div>
</div>

<!-- Stat strip -->
<section class="grid grid--stat mb-4" aria-label="Order counts">
  <div class="stat">
    <div class="stat__label">Preparing</div>
    <div class="stat__value"><?= (int)$statCounts['preparing'] ?></div>
    <div class="stat__delta muted">On the line</div>
  </div>
  <div class="stat">
    <div class="stat__label">Ready</div>
    <div class="stat__value"><?= (int)$statCounts['ready'] ?></div>
    <div class="stat__delta muted">Send to counter</div>
  </div>
</section>

<!-- Filter row -->
<div class="row mb-4" role="group" aria-label="Filter orders by status">
  <?php foreach ($filterPills as $key => $label): ?>
    <a class="btn btn--sm <?= $statusFilter === $key ? 'btn--gold' : 'btn--outline' ?>"
       href="/kitchen/?status=<?= e($key) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<!-- Orders table -->
<?php if (empty($orders)): ?>
  <div class="empty">
    <div class="empty__icon" aria-hidden="true">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M4 12h16M4 17h10"/></svg>
    </div>
    <h3>No orders here</h3>
    <p>There are no tickets matching this filter. New orders will appear automatically when they are placed.</p>
  </div>
<?php else: ?>
  <div class="table-wrap">
    <table class="tbl">
      <colgroup>
        <col style="width:96px">
        <col style="width:170px">
        <col>
        <col style="width:130px">
        <col style="width:104px">
        <col style="width:170px">
      </colgroup>
      <thead>
        <tr>
          <th>Order</th>
          <th>Customer</th>
          <th>Items</th>
          <th>Status</th>
          <th>Elapsed</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($orders as $id => $o): ?>
          <?php
            $s       = (string)($o['status'] ?? 'pending');
            [$lbl,$cls] = order_status_label($s);
            $elapsed = k_elapsed((string)($o['created_at'] ?? ''));
          ?>
          <tr>
            <td><span class="kbd">#<?= e(k_short_id((string)$id)) ?></span></td>
            <td><?= e(k_customer_name($o)) ?></td>
            <td><?= k_items_html($o['items'] ?? []) ?></td>
            <td><span class="badge <?= e($cls) ?>"><?= e($lbl) ?></span></td>
            <td class="muted"><?= e($elapsed) ?></td>
            <td>
              <?php if ($s === 'pending'): ?>
                <form method="post" action="/kitchen/" class="row">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="accept">
                  <input type="hidden" name="order_id" value="<?= e($id) ?>">
                  <button type="submit" class="btn btn--gold btn--sm">Accept</button>
                </form>
              <?php elseif ($s === 'accepted'): ?>
                <form method="post" action="/kitchen/" class="row">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="start">
                  <input type="hidden" name="order_id" value="<?= e($id) ?>">
                  <button type="submit" class="btn btn--outline btn--sm">Start cooking</button>
                </form>
              <?php elseif ($s === 'preparing'): ?>
                <form method="post" action="/kitchen/" class="row">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="ready">
                  <input type="hidden" name="order_id" value="<?= e($id) ?>">
                  <button type="submit" class="btn btn--ok btn--sm">Mark ready</button>
                </form>
              <?php elseif ($s === 'ready'): ?>
                <form method="post" action="/kitchen/" class="row">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="done">
                  <input type="hidden" name="order_id" value="<?= e($id) ?>">
                  <button type="submit" class="btn btn--ghost btn--sm"
                          data-confirm="Mark order #<?= e(k_short_id((string)$id)) ?> as done?">Mark done</button>
                </form>
              <?php else: ?>
                <span class="badge badge--muted">Completed</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
