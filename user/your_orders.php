<?php
/**
 * your_orders.php — Customer order & booking history.
 * Lists the customer's food orders and rent bookings, with status badges,
 * receipt links, and booking cancellation.
 */
require_once __DIR__ . '/../init.php';
require_user();
use App\Models\Order;
use App\Models\Booking;

$activeNav = 'orders';
$pageTitle = 'My Orders';
$layout    = 'wide';

/** Short summary of an items map: total qty + distinct kinds. */
function items_summary($items): string {
    if (!is_array($items) || empty($items)) return '—';
    $n = 0;
    foreach ($items as $row) {
        if (is_array($row)) $n += (int) ($row['qty'] ?? 0);
    }
    if ($n === 0) return '—';
    $kinds = count($items);
    return $n . ' item' . ($n === 1 ? '' : 's') . ' · ' . $kinds . ' kind' . ($kinds === 1 ? '' : 's');
}

/** Human-friendly datetime formatting. Output is escaped by the caller. */
function fmt_time(?string $t): string {
    if (!$t) return '—';
    $ts = strtotime($t);
    return $ts ? date('M j, Y \a\t g:i A', $ts) : $t;
}

$orders   = filter_by(Order::raw(),   'user_email', user_email());
$bookings = filter_by(Booking::raw(), 'user_email', user_email());

usort($orders,   function ($a, $b) { return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')); });
usort($bookings, function ($a, $b) { return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')); });

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
  <div class="page-head__row">
    <div>
      <span class="eyebrow">Your activity</span>
      <h1>My orders &amp; bookings</h1>
      <p>Track your food orders and rental reservations in one place.</p>
    </div>
    <div class="row">
      <a class="btn btn--outline btn--sm" href="#orders">Orders (<?= count($orders) ?>)</a>
      <a class="btn btn--outline btn--sm" href="#bookings">Rentals (<?= count($bookings) ?>)</a>
    </div>
  </div>
</div>

<!-- ============ FOOD ORDERS ============ -->
<section id="orders" class="section" style="margin-top:8px">
  <div class="section__head">
    <div>
      <span class="eyebrow">Food orders</span>
      <h2>Your orders</h2>
    </div>
    <a class="btn btn--ghost btn--sm" href="/user/products.php">Place another</a>
  </div>

  <?php if (!$orders): ?>
    <div class="empty">
      <div class="empty__icon" aria-hidden="true">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1z"/><path d="M8 7h8M8 11h8M8 15h5"/>
        </svg>
      </div>
      <h3>No orders yet</h3>
      <p>When you place an order, it will appear here.</p>
      <a class="btn btn--gold mt-2" href="/user/products.php">Browse the menu</a>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tbl">
        <thead>
          <tr>
            <th>Order</th>
            <th>Items</th>
            <th class="num">Total</th>
            <th>Progress</th>
            <th>Payment</th>
            <th>Placed</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($orders as $id => $o):
            [$pLabel, $pCls] = payment_status_label((string)($o['payment_status'] ?? ''));
            [$sLabel, $sCls] = order_status_label((string)($o['status'] ?? ''));
            $shortId = strtoupper(substr((string)$id, 0, 6));
            $placed  = fmt_time($o['created_at'] ?? null);
            $receipt = $o['receipt'] ?? null;
          ?>
            <tr>
              <td><code class="kbd"><?= e($shortId) ?></code><?php if (!empty($o['pickup_time'])): ?><br><small class="muted"><?= e($o['pickup_time']) ?></small><?php endif; ?></td>
              <td><?= e(items_summary($o['items'] ?? [])) ?></td>
              <td class="num"><?= money($o['total'] ?? 0) ?></td>
              <td><?= order_tracker_html((string)($o['status'] ?? '')) ?></td>
              <td><span class="badge <?= e($pCls) ?>"><?= e($pLabel) ?></span></td>
              <td class="muted"><?= e($placed) ?></td>
              <td class="t-right">
                <?php if (!empty($receipt)): ?>
                  <a class="thumb--link" href="<?= e(image_display_src($receipt, 'user/bookings')) ?>" target="_blank" rel="noopener" title="View receipt">
                    <img class="thumb" src="<?= e(image_display_src($receipt, 'user/bookings')) ?>" alt="Receipt thumbnail">
                  </a>
                <?php else: ?>
                  <span class="muted">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<!-- ============ RENT BOOKINGS ============ -->
<section id="bookings" class="section">
  <div class="section__head">
    <div>
      <span class="eyebrow">Rentals</span>
      <h2>Your bookings</h2>
    </div>
    <a class="btn btn--ghost btn--sm" href="/user/booking.php">New booking</a>
  </div>

  <?php if (!$bookings): ?>
    <div class="empty">
      <div class="empty__icon" aria-hidden="true">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18M8 2v4M16 2v4"/>
        </svg>
      </div>
      <h3>No bookings yet</h3>
      <p>Reserve tableware or equipment for your next event.</p>
      <a class="btn btn--gold mt-2" href="/user/booking.php">Browse rentals</a>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tbl">
        <thead>
          <tr>
            <th>Booking</th>
            <th>Items</th>
            <th>Appointment</th>
            <th>Return</th>
            <th class="num">Total</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($bookings as $id => $b):
            [$sLabel, $sCls] = booking_status_label((string)($b['status'] ?? ''));
            $shortId   = strtoupper(substr((string)$id, 0, 6));
            $canCancel = in_array((string)($b['status'] ?? ''), ['pending', 'accepted'], true);
            $receipt   = $b['receipt'] ?? null;
          ?>
            <tr>
              <td><code class="kbd"><?= e($shortId) ?></code></td>
              <td><?= e(items_summary($b['items'] ?? [])) ?></td>
              <td class="muted"><?= e(fmt_time($b['appointment_time'] ?? null)) ?></td>
              <td class="muted"><?= e(fmt_time($b['return_time'] ?? null)) ?></td>
              <td class="num"><?= money($b['total'] ?? 0) ?></td>
              <td><span class="badge <?= e($sCls) ?>"><?= e($sLabel) ?></span></td>
              <td class="t-right">
                <?php if ($canCancel): ?>
                  <a class="btn btn--ghost btn--sm" href="/user/booking.php?cancel=<?= e($id) ?>" data-confirm="Cancel this booking? Reserved stock will be returned.">Cancel</a>
                <?php elseif (!empty($receipt)): ?>
                  <a class="thumb--link" href="<?= e(image_display_src($receipt, 'user/bookings')) ?>" target="_blank" rel="noopener" title="View receipt">
                    <img class="thumb" src="<?= e(image_display_src($receipt, 'user/bookings')) ?>" alt="Receipt thumbnail">
                  </a>
                <?php else: ?>
                  <span class="muted">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
