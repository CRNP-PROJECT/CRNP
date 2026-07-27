<?php
/**
 * admin/bookings.php — Rent reservations overview + direct reservation form.
 */
require_once __DIR__ . '/../init.php';
require_admin();
use App\Models\Booking;
use App\Models\RentItem;

/* ---------- POST: create reservation ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $fullName   = trim((string) post('full_name', ''));
    $contact    = trim((string) post('contact', ''));
    $address    = trim((string) post('address', ''));
    $userEmail  = trim((string) post('user_email', ''));
    $apptRaw    = (string) post('appointment_time', '');
    $retRaw     = (string) post('return_time', '');
    $payMethod  = (string) post('payment_method', 'counter');

    // qty map (itemId => int)
    $qtys = [];
    foreach ($_POST as $k => $v) {
        if (strpos($k, 'qty_') === 0) {
            $itemId = substr($k, 4);
            $q = (int) $v;
            if ($q > 0) $qtys[$itemId] = $q;
        }
    }

    if ($fullName === '' || $contact === '') {
        flash('Customer name and contact are required.', 'danger');
        redirect('/admin/bookings.php#reserve');
    }
    if (!$qtys) {
        flash('Select at least one rent item with a quantity greater than 0.', 'danger');
        redirect('/admin/bookings.php#reserve');
    }
    if ($apptRaw === '') {
        flash('Appointment time is required.', 'danger');
        redirect('/admin/bookings.php#reserve');
    }

    // Re-fetch items and validate stock
    $rentItems = RentItem::raw();
    $items = [];
    $total = 0.0;
    $errors = [];
    foreach ($qtys as $itemId => $qty) {
        $row = $rentItems[$itemId] ?? null;
        if (!is_array($row)) {
            $errors[] = 'Item not found.';
            continue;
        }
        $onHand = (int) ($row['quantity'] ?? 0);
        if ($qty > $onHand) {
            $errors[] = 'Requested ' . $qty . ' × ' . ($row['display_name'] ?? $row['name'])
                      . ' but only ' . $onHand . ' in stock.';
            continue;
        }
        $price    = (float) ($row['price'] ?? 0);
        $subtotal = $price * $qty;
        $items[$itemId] = [
            'name'     => $row['display_name'] ?? $row['name'],
            'qty'      => $qty,
            'price'    => $price,
            'subtotal' => $subtotal,
        ];
        $total += $subtotal;
    }

    if ($errors) {
        flash(implode(' ', $errors), 'danger');
        redirect('/admin/bookings.php#reserve');
    }

    // Normalize datetimes
    $appt = $apptRaw;
    if ($ts = strtotime($apptRaw)) $appt = date('Y-m-d H:i:s', $ts);
    $ret = $retRaw ?: null;
    if ($ret !== null && ($ts = strtotime($retRaw))) $ret = date('Y-m-d H:i:s', $ts);

    $paymentStatus = $payMethod === 'gcash' ? 'pending_verification' : 'no_payment_required';

    $booking = [
        'user_email'      => $userEmail !== '' ? $userEmail : 'admin-reserved',
        'user_name'       => $fullName,
        'contact'         => $contact,
        'address'         => $address,
        'appointment_time'=> $appt,
        'return_time'     => $ret,
        'items'           => $items,
        'total'           => $total,
        'payment_method'  => $payMethod,
        'payment_status'  => $paymentStatus,
        'status'          => 'pending',
        'created_by'      => 'admin',
        'created_at'      => now(),
    ];

    try {
        (new Booking($booking))->save();
        // Decrement rent stock for each item
        foreach ($qtys as $itemId => $qty) {
            RentItem::decrementStock($itemId, $qty);
        }
        flash('Reservation created for ' . $fullName . '.', 'ok');
    } catch (Throwable $ex) {
        flash('Could not create reservation: ' . $ex->getMessage(), 'danger');
    }
    redirect('/admin/bookings.php');
}

/* ---------- List ---------- */
$page  = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$bookingPage = Booking::paginate($page, $perPage);
$bookings  = $bookingPage['data'];
$totalBookings = $bookingPage['total'];
$pages = $bookingPage['pages'];
$rentItems = RentItem::raw();

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
      <p>Review all rent reservations and create direct reservations on behalf of customers or walk-ins.</p>
    </div>
    <a class="btn btn--gold" href="#reserve">New reservation</a>
  </div>
</div>

<div class="layout-2">
  <!-- List -->
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
                    <dt>Payment</dt><dd><span class="badge <?= e($pc) ?>"><?= e($pl) ?></span> · <?= e(ucfirst((string) ($b['payment_method'] ?? 'counter'))) ?></dd>
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

  <!-- Reservation form -->
  <div class="card" id="reserve">
    <div class="card__head">
      <div><h2>New reservation</h2></div>
    </div>
    <div class="card__body">
      <?php if (!$rentItems): ?>
        <div class="empty">
          <div class="empty__icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7h18M3 12h18M3 17h18"/></svg>
          </div>
          <h3>No rent items</h3>
          <p>Add rent items first to create a reservation.</p>
        </div>
        <a class="btn btn--outline btn--block mt-4" href="/admin/rent_items.php">Manage rent items</a>
      <?php else: ?>
        <form method="post" action="/admin/bookings.php#reserve" class="form-grid">
          <?= csrf_field() ?>
          <div class="field">
            <label>Rent items</label>
            <div>
              <?php foreach ($rentItems as $rid => $r):
                $onHand = (int) ($r['quantity'] ?? 0);
                $lowClass = $onHand <= 2 ? 'low' : '';
                $img = product_image_url($r['image'] ?? '', $rid, 'rent_items');
              ?>
                <div class="item-row">
                  <img src="<?= e($img) ?>" alt="">
                  <div class="meta">
                    <strong><?= e($r['display_name'] ?? $r['name'] ?? 'Item') ?></strong>
                    <small><?= e(money((float) ($r['price'] ?? 0))) ?> · <span class="stock <?= $lowClass ?>"><?= $onHand ?> in stock</span></small>
                  </div>
                  <input class="input qty-input" type="number" min="0" max="<?= $onHand ?>" step="1"
                         name="qty_<?= e((string) $rid) ?>" value="0" placeholder="0">
                </div>
              <?php endforeach; ?>
            </div>
            <span class="hint">Set a quantity greater than 0 to include the item. 0 = skip.</span>
          </div>

          <div class="form-grid form-grid--2">
            <div class="field">
              <label for="full_name">Customer name</label>
              <input class="input" type="text" id="full_name" name="full_name" required value="<?= e(post('full_name','')) ?>" placeholder="Maria Santos">
            </div>
            <div class="field">
              <label for="contact">Contact</label>
              <input class="input" type="text" id="contact" name="contact" required value="<?= e(post('contact','')) ?>" placeholder="0917 123 4567">
            </div>
          </div>

          <div class="field">
            <label for="address">Address</label>
            <input class="input" type="text" id="address" name="address" value="<?= e(post('address','')) ?>" placeholder="Delivery / pickup address">
          </div>

          <div class="form-grid form-grid--2">
            <div class="field">
              <label for="appointment_time">Appointment time</label>
              <input class="input" type="datetime-local" id="appointment_time" name="appointment_time" required value="<?= e(post('appointment_time','')) ?>">
            </div>
            <div class="field">
              <label for="return_time">Return time</label>
              <input class="input" type="datetime-local" id="return_time" name="return_time" value="<?= e(post('return_time','')) ?>">
            </div>
          </div>

          <div class="form-grid form-grid--2">
            <div class="field">
              <label for="user_email">User email (optional)</label>
              <input class="input" type="email" id="user_email" name="user_email" value="<?= e(post('user_email','')) ?>" placeholder="customer@example.com">
              <span class="hint">Link to a specific user. Leave blank for walk-in.</span>
            </div>
            <div class="field">
              <label for="payment_method">Payment method</label>
              <?php $pm = (string) post('payment_method', 'counter'); ?>
              <select class="select" id="payment_method" name="payment_method">
                <option value="counter" <?= $pm==='counter' ? 'selected' : '' ?>>Pay at counter</option>
                <option value="gcash"   <?= $pm==='gcash'   ? 'selected' : '' ?>>GCash (verify later)</option>
              </select>
            </div>
          </div>

          <div class="form-actions">
            <button class="btn btn--gold btn--block" type="submit">Create reservation</button>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
