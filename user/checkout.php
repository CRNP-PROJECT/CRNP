<?php
/**
 * checkout.php — Customer order checkout.
 * Validates contact info, builds the order from the session cart, persists
 * to /orders, clears the cart, and emails a receipt.
 */
require_once __DIR__ . '/../init.php';
require_user();
use App\Models\Order;

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

/* ---------- Buy-Now mode: bypass cart when ?buy_now=1 ---------- */
$buyNowProductId = '';
$buyNowQty = 1;
if (isset($_GET['buy_now']) && $_GET['buy_now'] === '1') {
    $buyNowProductId = trim((string)($_GET['product_id'] ?? ''));
    $buyNowQty = max(1, (int)($_GET['qty'] ?? 1));
}

/* Guard: need either cart or buy-now item. */
$cart = get_cart();
if (!$cart && $buyNowProductId === '') {
    flash('Your cart is empty. Add a few dishes first.', 'warn');
    redirect('/user/products.php');
}

/* If buy-now mode, build a single-item "cart" so the rest of the page works. */
if ($buyNowProductId !== '' && !$cart) {
    $p = Product::find($buyNowProductId);
    if (!$p || ($p->status ?? 'available') !== 'available') {
        flash('Sorry, that item is not available.', 'warn');
        redirect('/user/products.php');
    }
    $cart = [
        $buyNowProductId => [
            'id'    => $buyNowProductId,
            'name'  => $p->name ?? 'Item',
            'price' => (float)($p->price ?? 0),
            'qty'   => $buyNowQty,
            'image' => $p->image ?? '',
        ],
    ];
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

    /* Refresh cart snapshot.
       In buy-now mode, rebuild the single-item cart from POST data. */
    $cart = get_cart();
    if (isset($_POST['buy_now']) && $_POST['buy_now'] === '1') {
        $bPid = trim((string)post('buy_now_product_id', ''));
        $bQty = max(1, (int)post('buy_now_qty', 1));
        if ($bPid !== '') {
            $bp = Product::find($bPid);
            if ($bp && ($bp->status ?? 'available') === 'available') {
                $cart = [
                    $bPid => [
                        'id'    => $bPid,
                        'name'  => $bp->name ?? 'Item',
                        'price' => (float)($bp->price ?? 0),
                        'qty'   => $bQty,
                        'image' => $bp->image ?? '',
                    ],
                ];
            }
        }
    }
    if (!$cart) {
        flash('Your cart emptied before checkout.', 'warn');
        redirect('/user/products.php');
    }

    /* Build items map { productId => {name, qty, price, subtotal} }. */
    $items = [];
    $total = 0.0;
    foreach ($cart as $pid => $item) {
        $qty   = (int)   ($item['qty']   ?? 1);
        $price = (float) ($item['price'] ?? 0);
        $sub   = $price * $qty;
        $items[$pid] = [
            'name'     => $item['name'] ?? 'Item',
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
            set_cart([]);

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
  <?php if ($buyNowProductId !== ''): ?>
    <input type="hidden" name="buy_now" value="1">
    <input type="hidden" name="buy_now_product_id" value="<?= e($buyNowProductId) ?>">
    <input type="hidden" name="buy_now_qty" value="<?= (int)$buyNowQty ?>">
  <?php endif; ?>

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
              <td class="num"><strong style="font-family:var(--sans);font-size:1.1rem"><?= money(cart_total()) ?></strong></td>
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
