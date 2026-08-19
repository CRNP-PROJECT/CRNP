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

/* ---------- Cancel order ---------- */
$cancelOrder = $_GET['cancel_order'] ?? post('cancel_order');
if ($cancelOrder) {
    $order = Order::find($cancelOrder);
    if (!$order) {
        flash('Order not found.', 'danger');
        redirect('/user/your_orders.php');
    }
    if (strcasecmp((string)($order->user_email ?? ''), user_email()) !== 0) {
        flash('You can only cancel your own orders.', 'danger');
        redirect('/user/your_orders.php');
    }
    if ((string)($order->status ?? '') !== 'pending') {
        flash('That order can no longer be cancelled.', 'warn');
        redirect('/user/your_orders.php');
    }
    try {
        $order->update([
            'status'       => 'cancelled',
            'cancelled_at' => now(),
        ]);
        flash('Order cancelled.', 'ok');
    } catch (Throwable $ex) {
        flash('Could not cancel order: ' . $ex->getMessage(), 'danger');
    }
    redirect('/user/your_orders.php');
}

$activeNav = 'orders';
$pageTitle = 'My Orders';
$layout    = 'wide';

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
            $method  = (string)($o['payment_method'] ?? '');
          ?>
            <tr>
              <td><code class="kbd"><?= e($shortId) ?></code><?php if (!empty($o['pickup_time'])): $pt = date('g:i A', strtotime($o['pickup_time'])); ?><br><small class="muted"><?= e($pt) ?></small><?php endif; ?></td>
              <td>
                <?= items_html($o['items'] ?? []) ?>
                <?php if (!empty($o['notes'])): ?>
                  <br><small class="note-badge"><?= e($o['notes']) ?></small>
                <?php endif; ?>
              </td>
              <td class="num"><?= money($o['total'] ?? 0) ?></td>
              <td><?= order_tracker_html((string)($o['status'] ?? '')) ?></td>
              <td>
                <span class="badge <?= e($pCls) ?>"><?= e($pLabel) ?></span>
                <?php if ($method === 'gcash' && !empty($receipt)): ?>
                  <br><span class="badge badge--gold" style="cursor:pointer;margin-top:4px;display:inline-block" data-receipt="<?= e(image_display_src($receipt, 'user/bookings')) ?>">View receipt</span>
                <?php endif; ?>
              </td>
              <td class="muted"><?= e($placed) ?></td>
              <td class="t-right">
                <?php if ((string)($o['status'] ?? '') === 'pending'): ?>
                  <a class="btn btn--ghost btn--sm" href="/user/your_orders.php?cancel_order=<?= e($id) ?>" data-confirm="Cancel this order?">Cancel</a>
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
            $canCancel = (string)($b['status'] ?? '') === 'pending';
            $receipt   = $b['receipt'] ?? null;
            $method    = (string)($b['payment_method'] ?? '');
          ?>
            <tr>
              <td><code class="kbd"><?= e($shortId) ?></code></td>
              <td>
                <?= items_html($b['items'] ?? []) ?>
                <?php if (!empty($b['notes'])): ?>
                  <br><small class="note-badge"><?= e($b['notes']) ?></small>
                <?php endif; ?>
              </td>
              <td class="muted"><?= e(fmt_time($b['appointment_time'] ?? null)) ?></td>
              <td class="muted"><?= e(fmt_time($b['return_time'] ?? null)) ?></td>
              <td class="num"><?= money($b['total'] ?? 0) ?></td>
              <td>
                <span class="badge <?= e($sCls) ?>"><?= e($sLabel) ?></span>
                <?php if ($method === 'gcash' && !empty($receipt)): ?>
                  <br><span class="badge badge--gold" style="cursor:pointer;margin-top:4px;display:inline-block" data-receipt="<?= e(image_display_src($receipt, 'user/bookings')) ?>">View receipt</span>
                <?php endif; ?>
              </td>
              <td class="t-right">
                <?php if ($canCancel): ?>
                  <a class="btn btn--ghost btn--sm" href="/user/booking.php?cancel=<?= e($id) ?>" data-confirm="Cancel this booking? Reserved stock will be returned.">Cancel</a>
                <?php endif; ?>

              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<!-- Receipt lightbox modal -->
<div id="receiptModal" class="receipt-modal" hidden>
  <div class="receipt-modal__inner">
    <button class="receipt-modal__close" type="button" aria-label="Close receipt preview">&times;</button>
    <img id="receiptModalImg" class="receipt-modal__img" alt="GCash receipt preview">
  </div>
</div>

<style>
  .note-badge { display:inline-block; margin-top:6px; padding:3px 8px; border-radius:6px; font-size:11px; background:var(--gold-100,#fbf3e0); color:var(--gold-600,#9a6b00); border:1px dashed var(--gold,#b8860b); }
  .receipt-modal { position:fixed; inset:0; z-index:10000; display:flex; align-items:center; justify-content:center; background:rgba(0,0,0,.7); }
  .receipt-modal[hidden] { display:none; }
  .receipt-modal__inner { position:relative; max-width:90vw; max-height:90vh; }
  .receipt-modal__img { display:block; max-width:90vw; max-height:90vh; border-radius:8px; box-shadow:0 8px 40px rgba(0,0,0,.5); }
  .receipt-modal__close { position:absolute; top:-36px; right:0; background:rgba(0,0,0,.5); color:#fff; border:0; border-radius:6px; width:32px; height:32px; font-size:20px; cursor:pointer; display:flex; align-items:center; justify-content:center; }
  .receipt-modal__close:hover { background:rgba(0,0,0,.7); }
</style>

<script>
(function() {
  var modal = document.getElementById('receiptModal');
  if (!modal) return;
  var modalImg = document.getElementById('receiptModalImg');
  var closeBtn = modal.querySelector('.receipt-modal__close');
  function open(src) { modalImg.src = src; modal.removeAttribute('hidden'); }
  function close() { modal.setAttribute('hidden', ''); modalImg.src = ''; }
  closeBtn.addEventListener('click', close);
  modal.addEventListener('click', function(e) { if (e.target === modal) close(); });
  document.addEventListener('keydown', function(e) { if (e.key === 'Escape') close(); });
  document.querySelectorAll('[data-receipt]').forEach(function(btn) {
    btn.addEventListener('click', function() { open(btn.getAttribute('data-receipt')); });
  });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
