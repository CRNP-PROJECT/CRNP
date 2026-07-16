<?php
/**
 * checkout.php — Customer order checkout.
 * Validates contact info, builds the order from the session cart, persists
 * to /orders, decrements stock, clears the cart, and emails a receipt.
 */
require_once __DIR__ . '/../init.php';
require_user();
use App\Models\Order;
use App\Models\Product;

$db = getDB();
$activeNav = 'shop';
$pageTitle = 'Checkout';
$layout    = 'narrow';

/* ---------- Prefill: pull stored profile fields for the current user ----------
 * Falls back to session-level name/email when no /user record exists yet
 * or when individual fields are missing. */
$profileName    = user_name();
$profileContact = '';
$userId = $_SESSION['user_id'] ?? '';
if ($userId !== '') {
    $rec = $db->retrieve('/user/' . $userId);
    if (is_array($rec)) {
        if (!empty($rec['name']))    $profileName    = (string) $rec['name'];
        if (!empty($rec['contact'])) $profileContact = (string) $rec['contact'];
    }
}

/* Build pickup-time options: Tue–Sun, 11:00–21:30 in 30-min slots. */
$pickupSlots = [];
for ($m = 11 * 60; $m <= 21 * 60 + 30; $m += 30) {
    $pickupSlots[] = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
}

/* Reservation date = today only. */
$reservationDate = date('Y-m-d');

/* Guard: empty cart can't be checked out. */
$cart = get_cart();
if (!$cart) {
    flash('Your cart is empty. Add a few dishes first.', 'warn');
    redirect('/user/products.php');
}

/* ---------- POST: confirm checkout ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $full_name = trim(post('full_name'));
    $contact   = trim(post('contact'));
    $pickup    = trim(post('pickup_time', ''));
    $method    = post('payment_method', 'counter');
    if (!in_array($method, ['gcash', 'counter'], true)) {
        $method = 'counter';
    }

    $errors = [];
    if ($full_name === '') $errors[] = 'Please enter your full name.';
    if ($contact   === '') $errors[] = 'Please enter a contact number.';
    if ($pickup === '' || !in_array($pickup, $pickupSlots, true)) {
        $errors[] = 'Please choose a pickup time.';
    }

    /* Refresh cart snapshot so we don't trust stale stock. */
    $cart = get_cart();
    if (!$cart) {
        flash('Your cart emptied before checkout.', 'warn');
        redirect('/user/products.php');
    }

    /* Validate stock against live products. */
    $liveProducts = rows($db->retrieve('/products'));
    foreach ($cart as $pid => $item) {
        $qty   = (int)($item['qty'] ?? 1);
        $stock = (int)(($liveProducts[$pid] ?? [])['stock'] ?? 0);
        if (!isset($liveProducts[$pid])) {
            $errors[] = 'A product in your cart is no longer available.';
        } elseif ($qty > $stock) {
            $errors[] = 'Only ' . $stock . ' × "' . ($liveProducts[$pid]['name'] ?? 'Item') . '" left (requested ' . $qty . ').';
        }
    }
    if ($errors) {
        foreach ($errors as $err) flash($err, 'danger');
        redirect('/user/checkout.php');
    }

    /* Build items map { productId => {name, qty, price, subtotal} }. */
    $items = [];
    $total = 0.0;
    foreach ($cart as $pid => $item) {
        $qty   = (int)   ($item['qty']   ?? 1);
        $lp    = $liveProducts[$pid] ?? [];
        $price = (float) ($lp['price'] ?? $item['price'] ?? 0);
        $sub   = $price * $qty;
        $items[$pid] = [
            'name'     => $lp['name'] ?? $item['name'] ?? 'Item',
            'qty'      => $qty,
            'price'    => $price,
            'subtotal' => $sub,
        ];
        $total += $sub;
    }

    /* Receipt upload (only when everything else validates). */
    $receipt = null;
    if (!$errors && $method === 'gcash') {
        if (!isset($_FILES['receipt']) || $_FILES['receipt']['error'] === UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Please upload your GCash receipt.';
        } else {
            try {
                $receipt = upload_to_base64('receipt', UPLOAD_ROOT . '/user/bookings');
                if (!$receipt) {
                    $errors[] = 'Receipt upload failed. Please try again.';
                }
            } catch (Throwable $ex) {
                $errors[] = $ex->getMessage();
            }
        }
    }

    if (!$errors) {
        $payment_status = $method === 'gcash' ? 'pending_verification' : 'no_payment_required';
        $order = [
            'user_id'          => $_SESSION['user_id'] ?? '',
            'user_email'       => user_email(),
            'user_name'        => user_name(),
            'items'            => $items,
            'total'            => $total,
            'full_name'        => $full_name,
            'contact'          => $contact,
            'reservation_date' => $reservationDate,
            'pickup_time'      => $pickup,
            'payment_method'   => $method,
            'payment_status'   => $payment_status,
            'payment_verified' => false,
            'receipt'          => $receipt,
            'status'           => 'pending',
            'created_at'       => now(),
        ];

        try {
            $newId = (new Order($order))->save();
            if (!$newId) {
                throw new RuntimeException('Firebase did not return an order id.');
            }
            /* Decrement stock only after a successful insert. */
            foreach ($items as $pid => $row) {
                Product::decrementStock($pid, (int) $row['qty']);
            }
            set_cart([]);

            /* P0: send a branded receipt email. Order is already saved, so a
               mail failure must NOT fail the checkout — warn the customer. */
            try {
                $orderData = $order;
                $orderData['id'] = $newId;
                $customerEmail = $order['user_email'] ?: user_email();
                if ($customerEmail !== '') {
                    $sent = sendOrderReceipt($customerEmail, $orderData);
                    if (!$sent) {
                        flash('Order placed, but confirmation email could not be sent.', 'warn');
                    }
                }
            } catch (Throwable $mailEx) {
                error_log('[checkout] sendOrderReceipt failed for order ' . $newId . ': ' . $mailEx->getMessage());
                flash('Order placed, but confirmation email could not be sent.', 'warn');
            }

            flash('Reservation placed! We will be in touch shortly.', 'ok');
            redirect('/user/your_orders.php');
        } catch (Throwable $ex) {
            flash('Could not place your order: ' . $ex->getMessage(), 'danger');
        }
    } else {
        foreach ($errors as $err) {
            flash($err, 'danger');
        }
    }
}

/* Re-fetch cart in case it changed (e.g., empty mid-flow). */
$cart = get_cart();
if (!$cart) {
    flash('Your cart is empty. Add a few dishes first.', 'warn');
    redirect('/user/products.php');
}

require_once __DIR__ . '/../includes/header.php';
?>

  <div class="page-head">
  <span class="eyebrow">Checkout · Step 2 of 2</span>
  <h1>Confirm your reservation</h1>
  <p>Almost there. Fill in your details and preferred pickup time for today.</p>
</div>

<form method="post" enctype="multipart/form-data" class="form-grid" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="confirmCheckout" value="1">

  <div class="card">
    <div class="card__head"><h2>Order summary</h2><small class="muted"><?= count($cart) ?> item<?= count($cart) === 1 ? '' : 's' ?></small></div>
    <div class="card__body" style="padding:0">
      <div class="table-wrap" style="border:0">
        <table class="tbl">
          <thead>
            <tr><th>Item</th><th class="num">Qty</th><th class="num">Unit</th><th class="num">Subtotal</th></tr>
          </thead>
          <tbody>
            <?php foreach ($cart as $pid => $item):
              $qty  = (int)   ($item['qty']   ?? 1);
              $unit = (float) ($item['price'] ?? 0);
              $sub  = $unit * $qty;
            ?>
              <tr>
                <td><?= e($item['name'] ?? 'Item') ?></td>
                <td class="num"><?= $qty ?></td>
                <td class="num"><?= money($unit) ?></td>
                <td class="num"><?= money($sub) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="3" class="t-right muted" style="text-transform:uppercase;font-size:12px;letter-spacing:.08em">Total</td>
              <td class="num"><strong style="font-family:var(--serif);font-size:1.1rem"><?= money(cart_total()) ?></strong></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>

  <div class="card card--pad">
    <div class="card__head" style="padding:0 0 14px;border-bottom:1px solid var(--line-2);margin-bottom:18px"><h2>Reservation &amp; payment</h2></div>

    <div class="form-grid">
      <div class="form-grid--2">
        <div class="field">
          <label for="full_name">Full name</label>
          <input class="input" type="text" id="full_name" name="full_name" value="<?= e(post('full_name', $profileName)) ?>" required autocomplete="name">
        </div>
        <div class="field">
          <label for="contact">Contact number</label>
          <input class="input" type="tel" id="contact" name="contact" value="<?= e(post('contact', $profileContact)) ?>" required autocomplete="tel" placeholder="0917 000 0000">
        </div>
      </div>

      <div class="field">
        <label>Reservation date</label>
        <input class="input" type="text" value="<?= e(date('l, F j, Y')) ?>" readonly style="background:var(--bg-2);cursor:default">
        <span class="hint">All reservations are for today only.</span>
      </div>

      <div class="field">
        <label for="pickup_time">Pickup time</label>
        <select class="select" id="pickup_time" name="pickup_time" required>
          <option value="" disabled <?= post('pickup_time', '') === '' ? 'selected' : '' ?>>Choose a pickup time</option>
          <?php foreach ($pickupSlots as $slot):
            $label = date('g:i A', strtotime($slot));
            $sel   = post('pickup_time', '') === $slot ? 'selected' : '';
          ?>
            <option value="<?= e($slot) ?>" <?= $sel ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <span class="hint">Pick up at our Iloilo City counter. Open Tue–Sun, 11:00 AM – 10:00 PM.</span>
      </div>

      <div class="field">
        <label>Payment method</label>
        <div class="row">
          <label class="checkbox-row"><input type="radio" name="payment_method" value="counter" <?= post('payment_method', 'counter') === 'counter' ? 'checked' : '' ?> data-pay="counter"> Pay at counter</label>
          <label class="checkbox-row"><input type="radio" name="payment_method" value="gcash"   <?= post('payment_method')          === 'gcash'   ? 'checked' : '' ?> data-pay="gcash"> GCash</label>
        </div>
      </div>

      <div class="field" id="receipt-field" style="display:none">
        <label for="receipt">GCash receipt</label>
        <input class="input" type="file" id="receipt" name="receipt" accept="image/png,image/jpeg,image/webp">
        <span class="hint">Upload a screenshot of your GCash transfer. JPG, PNG, or WebP (max 5MB).</span>
      </div>
    </div>

    <div class="form-actions" style="margin-top:20px">
      <button class="btn btn--gold btn--lg" type="submit">Place reservation</button>
      <a class="btn btn--ghost" href="/user/cart.php">Back to cart</a>
    </div>
  </div>
</form>

<script>
(function () {
  var counter = document.querySelector('[data-pay="counter"]');
  var gcash   = document.querySelector('[data-pay="gcash"]');
  var field   = document.getElementById('receipt-field');
  var receipt = document.getElementById('receipt');
  if (!counter || !gcash || !field || !receipt) return;
  function sync() {
    var isGcash = gcash.checked;
    field.style.display   = isGcash ? '' : 'none';
    receipt.required      = isGcash;
  }
  counter.addEventListener('change', sync);
  gcash.addEventListener('change', sync);
  sync();
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
