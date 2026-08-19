<?php
/**
 * cashier/order_now.php — POS / walk-in order creation.
 * Cashier browses products, adds to a running order, fills customer details,
 * picks a payment method (gcash with optional receipt, or counter), and
 * submits. The order lands in the orders queue as 'accepted'.
 */
use App\Models\Order;
use App\Models\Product;

require_once __DIR__ . '/../init.php';
require_cashier();

$cashierName = $_SESSION['cashier_name'] ?? 'Cashier';
$products    = Product::raw();
$cats = [];
foreach ($products as $p) {
    if (!empty($p['category'])) $cats[$p['category']] = true;
}
ksort($cats);
uasort($products, fn($a,$b) => strcasecmp($a['category']??'', $b['category']??''));

/* ---------- POST: create walk-in order ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $fullName      = trim((string)post('full_name', ''));
    $tableNumber   = trim((string)post('table_number', ''));
    $numCustomers  = max(1, (int)post('num_customers', 1));
    $cashTendered  = (float)post('cash_tendered', 0);
    $notes         = trim((string)post('notes', ''));
    $paymentMethod = (string)post('payment_method', 'counter');
    if (!in_array($paymentMethod, ['gcash', 'counter'], true)) {
        $paymentMethod = 'counter';
    }
    $qtyMap = post('qty', []);
    if (!is_array($qtyMap)) {
        $qtyMap = [];
    }

    $errors = [];
    if ($fullName === '') {
        $errors[] = 'Customer name is required.';
    }
    if ($tableNumber === '') {
        $errors[] = 'Table number is required.';
    }
    if ($numCustomers < 1) {
        $errors[] = 'Number of customers must be at least 1.';
    }
    if ($paymentMethod === 'counter' && $cashTendered <= 0) {
        $errors[] = 'Please enter the cash amount tendered.';
    }

    $productsList = Product::raw();

    $items  = [];
    $total  = 0.0;
    $anyQty = false;
    foreach ($qtyMap as $productId => $qty) {
        $productId = (string)$productId;
        $qty       = (int)$qty;
        if ($qty <= 0 || !isset($productsList[$productId]) || !is_array($productsList[$productId])) {
            continue;
        }
        $anyQty = true;
        $p      = $productsList[$productId];
        $price    = (float)($p['price'] ?? 0);
        $subtotal = $price * $qty;
        $items[$productId] = [
            'name'     => (string)($p['name'] ?? 'Item'),
            'qty'      => $qty,
            'price'    => $price,
            'subtotal' => $subtotal,
        ];
        $total += $subtotal;
    }
    if (!$anyQty) {
        $errors[] = 'Add at least one product with a quantity.';
    }

    // Optional GCash receipt upload.
    $receiptFile = null;
    if ($paymentMethod === 'gcash') {
        try {
            $receiptFile = upload_to_base64('receipt', UPLOAD_ROOT . '/user/bookings');
        } catch (Throwable $ex) {
            $errors[] = 'Receipt upload failed: ' . $ex->getMessage();
        }
    }

    if ($errors) {
        foreach ($errors as $msg) {
            flash($msg, 'danger');
        }
        // Fall through to re-render form with preserved inputs.
    } else {
        $change = $paymentMethod === 'counter' ? max(0, $cashTendered - $total) : 0;
        $acceptedAt = now();

        $order = [
            'customer_name'    => $fullName,
            'user_name'        => $fullName,
            'user_email'       => 'walk-in',
            'user_id'          => '',
            'table_number'     => $tableNumber,
            'num_customers'    => $numCustomers,
            'items'            => $items,
            'total'            => $total,
            'notes'            => $notes,
            'cash_tendered'    => $cashTendered,
            'change'           => $change,
            'payment_method'   => $paymentMethod,
            'payment_status'   => 'paid',
            'payment_verified' => true,
            'verified_at'      => $acceptedAt,
            'verified_by'      => $cashierName,
            'receipt'          => $receiptFile,
            'gcash_receipt'    => $receiptFile,
            'status'           => 'accepted',
            'accepted_at'      => $acceptedAt,
            'accepted_by'      => $cashierName,
            'created_at'       => $acceptedAt,
            'placed_at'        => $acceptedAt,
            'created_by'       => $cashierName,
            'source'           => 'walk-in',
        ];

        $orderObj = new Order($order);
        $newId = $orderObj->save();
        if (!$newId) {
            flash('Could not create the order. Please try again.', 'danger');
        } else {
            flash('Walk-in order #' . substr($newId, 0, 6) . ' created for ' . $fullName . ' (Table ' . $tableNumber . ', ' . $numCustomers . ' pax).', 'ok');
            redirect('/cashier/receipt.php?id=' . $newId);
        }
    }
}

$pageTitle = 'POS';
$activeNav = 'ordernow';
$layout    = 'wide';
require_once __DIR__ . '/../includes/header.php';

// Build a JSON-safe product list for the JS POS cart.
$jsProducts = [];
foreach ($products as $pid => $p) {
    $jsProducts[$pid] = [
        'id'    => $pid,
        'name'  => $p['name'] ?? 'Item',
        'price' => (float)($p['price'] ?? 0),
            'image' => product_image_url($p['image'] ?? '', $pid, 'products'),
        'cat'   => $p['category'] ?? '',
    ];
}
?>

<style>
  .pos { display:grid; grid-template-columns:1fr; gap:24px; }
  @media(min-width:901px){ .pos{grid-template-columns:1fr 380px;} }
  .pos__browse {}
  .pos__order { position:sticky; top:80px; align-self:start; }

  .pos-search { display:flex; gap:8px; margin-bottom:16px; }
  .pos-search .input { flex:1; }

  .pos-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:12px; }
  .pos-cats { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:10px; }
  .pos-cat { font-size:12px; font-weight:600; padding:3px 12px; border-radius:999px;
    border:1px solid var(--line,#e6dfd1); background:var(--surface,#fff);
    cursor:pointer; color:var(--muted); transition:.15s; }
  .pos-cat:hover { background:var(--gold-100); border-color:var(--gold); color:var(--gold); }
  .pos-cat.is-active { background:var(--gold); color:#fff; border-color:var(--gold); }
  .pos-item {
    border:1px solid var(--line,#e6dfd1); border-radius:12px; overflow:hidden;
    background:var(--surface,#fff); cursor:pointer; transition:box-shadow .15s,border-color .15s;
    position:relative; box-shadow:var(--shadow-sm);
  }
  .pos-item:hover { border-color:var(--gold); box-shadow:var(--shadow); }
  .pos-item.is-soldout { opacity:.5; cursor:not-allowed; }
  .pos-item__img { width:100%; aspect-ratio:4/3; object-fit:cover; display:block; background:var(--muted,#f5f0e8); }
  .pos-item__body { padding:10px 12px; }
  .pos-item__name { font-weight:600; font-size:14px; margin:0 0 2px; }
  .pos-item__meta { display:flex; justify-content:space-between; align-items:center; }
  .pos-item__price { font-weight:700; color:var(--gold,#b8860b); font-size:14px; }
  .pos-item__stock { font-size:11px; }
  .pos-item__stock.out { color:var(--danger,#c0392b); }
  .pos-item__stock.low { color:var(--warn,#d4850a); }
  .pos-item__cat { position:absolute; top:8px; left:8px; font-size:10px; font-weight:600;
    text-transform:uppercase; letter-spacing:.06em; padding:2px 8px; border-radius:999px;
    background:var(--gold-100); color:var(--gold-600); }

  /* Order panel */
  .order-panel { border:1px solid var(--line,#e6dfd1); border-radius:14px; background:var(--surface,#fff); overflow:hidden; }
  .order-panel__head { padding:16px 20px; border-bottom:1px solid var(--line,#e6dfd1); display:flex; justify-content:space-between; align-items:center; }
  .order-panel__head h2 { margin:0; font-size:18px; }
  .order-panel__items { max-height:320px; overflow-y:auto; padding:0; }
  .order-line { display:flex; align-items:center; gap:10px; padding:10px 20px; border-bottom:1px solid var(--line,#e6dfd1); }
  .order-line:last-child { border-bottom:0; }
  .order-line__info { flex:1; min-width:0; }
  .order-line__name { font-weight:600; font-size:13px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .order-line__price { font-size:12px; color:var(--muted); }
  .order-line__qty { display:flex; align-items:center; gap:4px; }
  .order-line__qty button {
    width:26px; height:26px; border-radius:6px; border:1px solid var(--line,#e6dfd1);
    background:var(--surface,#fff); cursor:pointer; font-size:14px; font-weight:700;
    display:flex; align-items:center; justify-content:center; color:var(--ink);
  }
  .order-line__qty button:hover { background:var(--muted,#f5f0e8); }
  .order-line__qty span { min-width:24px; text-align:center; font-weight:600; font-size:14px; }
  .order-line__sub { font-weight:700; font-size:13px; white-space:nowrap; }
  .order-line__remove { background:none; border:0; color:var(--danger,#c0392b); cursor:pointer; font-size:18px; padding:0 2px; line-height:1; }

  .order-panel__empty { padding:32px 20px; text-align:center; color:var(--muted); font-size:14px; }
  .order-panel__foot { padding:16px 20px; border-top:1px solid var(--line,#e6dfd1); }
  .order-total { display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; }
  .order-total__label { font-size:13px; text-transform:uppercase; letter-spacing:.06em; color:var(--muted); }
  .order-total__val { font-family:var(--sans); font-size:1.5rem; font-weight:700; }

  .pos-customer { padding:16px 20px; border-top:1px solid var(--line,#e6dfd1); }
  .pos-customer .field { margin-bottom:10px; }
  .pos-customer .field:last-child { margin-bottom:0; }
  .pos-customer label { font-size:12px; font-weight:600; margin-bottom:4px; display:block; }

  @media(max-width:900px){
    .pos__order { position:static; }
    .pos-grid { grid-template-columns:repeat(auto-fill,minmax(140px,1fr)); }
  }
</style>

<header class="page-head">
  <div class="page-head__row">
    <div>
      <span class="eyebrow">Cashier Console</span>
      <h1>POS</h1>
      <p>Create a walk-in food order. Tap products to add, fill in customer and table details, then submit.</p>
    </div>
    <a class="btn btn--outline btn--sm" href="/cashier/">&larr; Back to orders</a>
  </div>
</header>

<?php if (!$products): ?>
  <div class="empty">
    <div class="empty__icon" aria-hidden="true">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
    </div>
    <h3>No products available</h3>
    <p>An administrator must add products before you can create an order.</p>
  </div>
<?php else: ?>
<form method="post" enctype="multipart/form-data" id="posForm">
  <?= csrf_field() ?>
  <input type="hidden" name="qty" id="qtyPayload" value="">

  <div class="pos">
    <!-- Left: product browser -->
    <div class="pos__browse">
      <div class="pos-search">
        <input class="input" type="search" id="posSearch" placeholder="Search products…" aria-label="Search products">
      </div>
<?php if ($cats): ?>
      <div class="pos-cats" id="posCats">
        <button type="button" class="pos-cat is-active" data-cat="">All</button>
        <?php foreach (array_keys($cats) as $c): ?>
          <button type="button" class="pos-cat" data-cat="<?= e($c) ?>"><?= e($c) ?></button>
        <?php endforeach; ?>
      </div>
<?php endif; ?>
      <div class="pos-grid" id="posGrid">
        <?php foreach ($products as $pid => $p):
            $isUnavailable = ($p['status'] ?? 'available') !== 'available';
            $img     = product_image_url($p['image'] ?? '', $pid, 'products');
        ?>
          <div class="pos-item <?= $isUnavailable ? 'is-soldout' : '' ?>"
               data-pid="<?= e($pid) ?>"
               data-name="<?= e($p['name'] ?? 'Item') ?>"
               data-price="<?= (float)($p['price'] ?? 0) ?>"
               data-img="<?= e($img) ?>"
               data-cat="<?= e($p['category'] ?? '') ?>"
               <?= $isUnavailable ? '' : 'tabindex="0" role="button" aria-label="Add ' . e($p['name'] ?? 'item') . ' to order"' ?>>
            <?php if (!empty($p['category'])): ?>
              <span class="pos-item__cat"><?= e($p['category']) ?></span>
            <?php endif; ?>
            <img class="pos-item__img" src="<?= e($img) ?>" alt="<?= e($p['name'] ?? 'Item') ?>" loading="lazy">
            <div class="pos-item__body">
              <p class="pos-item__name"><?= e($p['name'] ?? 'Untitled') ?></p>
              <div class="pos-item__meta">
                <span class="pos-item__price"><?= e(money($p['price'] ?? 0)) ?></span>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Right: order panel -->
    <div class="pos__order">
      <div class="order-panel">
        <div class="order-panel__head">
          <h2>Current Order</h2>
          <span class="badge badge--info" id="itemCount">0 items</span>
        </div>

        <div class="order-panel__items" id="orderItems">
          <div class="order-panel__empty" id="orderEmpty">Tap a product to add it to the order.</div>
        </div>

        <div class="order-panel__foot">
          <div class="order-total">
            <span class="order-total__label">Total</span>
            <span class="order-total__val" id="orderTotal">₱0.00</span>
          </div>
        </div>

        <div class="pos-customer">
          <div class="field">
            <label for="full_name">Customer name *</label>
            <input class="input" type="text" id="full_name" name="full_name" required
                   value="<?= e(post('full_name')) ?>" placeholder="Walk-in customer">
          </div>
          <div class="form-grid form-grid--2">
            <div class="field">
              <label for="table_number">Table no. *</label>
              <input class="input" type="text" id="table_number" name="table_number" required
                     value="<?= e(post('table_number')) ?>" placeholder="e.g. 1, 2A">
            </div>
            <div class="field">
              <label for="num_customers">No. of customers *</label>
              <input class="input" type="number" id="num_customers" name="num_customers" min="1" max="50" required
                     value="<?= e(post('num_customers', '1')) ?>">
            </div>
          </div>
          <div class="field">
            <label for="payment_method">Payment method</label>
            <select class="select" id="payment_method" name="payment_method">
              <option value="counter" <?= post('payment_method') === 'counter' ? 'selected' : '' ?>>Pay at counter</option>
              <option value="gcash" <?= post('payment_method') === 'gcash' ? 'selected' : '' ?>>GCash</option>
            </select>
          </div>
          <div class="field" id="cash-field">
            <label for="cash_tendered">Cash tendered</label>
            <input class="input" type="number" id="cash_tendered" name="cash_tendered" min="0" step="0.01"
                   value="<?= e(post('cash_tendered', '')) ?>" placeholder="0.00">
          </div>
          <div class="field" id="change-display" style="display:none">
            <label>Change</label>
            <div style="font-family:var(--sans);font-size:1.3rem;font-weight:700;color:var(--ok,#16a34a)" id="changeValue">₱0.00</div>
          </div>
          <div class="field" id="receipt-field" style="display:none">
            <label for="receipt">GCash receipt</label>
            <input class="input" type="file" id="receipt" name="receipt" accept="image/jpeg,image/png,image/webp">
            <span class="hint">JPG, PNG, or WEBP. Max 5 MB.</span>
          </div>
          <div class="field">
            <label for="notes">Special instructions</label>
            <textarea class="textarea" id="notes" name="notes" rows="2" placeholder="e.g. No lettuce, extra rice"><?= e(post('notes')) ?></textarea>
          </div>

          <div class="form-actions mt-4">
            <button type="submit" class="btn btn--gold btn--lg btn--block" id="submitBtn" disabled>Place order</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</form>
<?php endif; ?>

<script>
(function () {
  var products = <?= json_encode($jsProducts, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  var cart     = {};  // pid => { name, price, qty, img }
  var form     = document.getElementById('posForm');
  var grid     = document.getElementById('posGrid');
  var itemsEl  = document.getElementById('orderItems');
  var emptyEl  = document.getElementById('orderEmpty');
  var totalEl  = document.getElementById('orderTotal');
  var countEl  = document.getElementById('itemCount');
  var qtyField = document.getElementById('qtyPayload');
  var submitBtn= document.getElementById('submitBtn');
  var searchEl = document.getElementById('posSearch');
  var pmSelect = document.getElementById('payment_method');
  var recField = document.getElementById('receipt-field');
  var cashField = document.getElementById('cash-field');
  var cashInput = document.getElementById('cash_tendered');
  var changeDisp = document.getElementById('change-display');
  var changeVal  = document.getElementById('changeValue');

  if (!form || !grid) return;

  /* ---- cash/change calculation ---- */
  function calcChange() {
    if (!cashInput || !changeDisp || !changeVal) return;
    if (pmSelect && pmSelect.value === 'gcash') {
      cashField.style.display = 'none';
      changeDisp.style.display = 'none';
      return;
    }
    cashField.style.display = '';
    var tendered = parseFloat(cashInput.value) || 0;
    var total = 0;
    var keys = Object.keys(cart);
    for (var i = 0; i < keys.length; i++) {
      var it = cart[keys[i]];
      total += it.price * it.qty;
    }
    if (tendered > 0 && tendered >= total) {
      var ch = tendered - total;
      changeVal.textContent = fmtMoney(ch);
      changeDisp.style.display = '';
    } else {
      changeDisp.style.display = 'none';
    }
  }

  /* ---- add to cart ---- */
  function addToCart(pid) {
    if (!products[pid]) return;
    if (cart[pid]) {
      cart[pid].qty++;
    } else {
      var p = products[pid];
      cart[pid] = { name:p.name, price:p.price, qty:1, img:p.image };
    }
    render();
  }

  /* ---- adjust qty ---- */
  function adjustQty(pid, delta) {
    if (!cart[pid]) return;
    cart[pid].qty += delta;
    if (cart[pid].qty <= 0) { delete cart[pid]; }
    render();
  }

  /* ---- remove ---- */
  function removeItem(pid) { delete cart[pid]; render(); }

  /* ---- render order panel ---- */
  function render() {
    var keys = Object.keys(cart);
    var total = 0, count = 0;
    var html = '';

    for (var i = 0; i < keys.length; i++) {
      var k = keys[i], it = cart[k];
      var sub = it.price * it.qty;
      total += sub;
      count += it.qty;
      html += '<div class="order-line">'
            + '<img src="' + esc(it.img) + '" alt="" style="width:40px;height:40px;border-radius:8px;object-fit:cover">'
            + '<div class="order-line__info">'
            +   '<div class="order-line__name">' + esc(it.name) + '</div>'
            +   '<div class="order-line__price">' + fmtMoney(it.price) + ' each</div>'
            + '</div>'
            + '<div class="order-line__qty">'
            +   '<button type="button" onclick="posAdjust(\'' + k + '\',-1)" aria-label="Decrease">&minus;</button>'
            +   '<span>' + it.qty + '</span>'
            +   '<button type="button" onclick="posAdjust(\'' + k + '\',1)" aria-label="Increase">+</button>'
            + '</div>'
            + '<div class="order-line__sub">' + fmtMoney(sub) + '</div>'
            + '<button type="button" class="order-line__remove" onclick="posRemove(\'' + k + '\')" aria-label="Remove">&times;</button>'
            + '</div>';
    }

    if (keys.length === 0) {
      emptyEl.style.display = '';
      itemsEl.innerHTML = '';
    } else {
      emptyEl.style.display = 'none';
      itemsEl.innerHTML = html;
    }

    totalEl.textContent = fmtMoney(total);
    countEl.textContent = count + ' item' + (count === 1 ? '' : 's');
    submitBtn.disabled = keys.length === 0;
    calcChange();
  }

  /* ---- format peso ---- */
  function fmtMoney(n) { return '\u20B1' + Number(n).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','); }
  function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

  /* ---- product card click ---- */
  grid.addEventListener('click', function (e) {
    var card = e.target.closest('.pos-item');
    if (!card || card.classList.contains('is-soldout')) return;
    addToCart(card.dataset.pid);
  });
  grid.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' || e.key === ' ') {
      var card = e.target.closest('.pos-item');
      if (!card || card.classList.contains('is-soldout')) return;
      e.preventDefault();
      addToCart(card.dataset.pid);
    }
  });

  /* ---- search filter ---- */
  if (searchEl) {
    searchEl.addEventListener('input', function () {
      var q = searchEl.value.toLowerCase();
      var cards = grid.querySelectorAll('.pos-item');
      for (var i = 0; i < cards.length; i++) {
        var c = cards[i];
        var match = c.dataset.name.toLowerCase().indexOf(q) !== -1
                 || c.dataset.cat.toLowerCase().indexOf(q) !== -1;
        c.style.display = match ? '' : 'none';
      }
    });
  }

  /* ---- payment method toggle ---- */
  if (pmSelect) {
    pmSelect.addEventListener('change', function () {
      recField.style.display = pmSelect.value === 'gcash' ? '' : 'none';
      calcChange();
    });
  }
  if (cashInput) {
    cashInput.addEventListener('input', calcChange);
  }

  /* ---- form submit: serialize cart into qty inputs ---- */
  form.addEventListener('submit', function (e) {
    var keys = Object.keys(cart);
    if (keys.length === 0) { e.preventDefault(); return; }
    var existing = form.querySelectorAll('input[name^="qty["]');
    for (var x = 0; x < existing.length; x++) existing[x].remove();
    for (var i = 0; i < keys.length; i++) {
      var inp = document.createElement('input');
      inp.type = 'hidden';
      inp.name = 'qty[' + keys[i] + ']';
      inp.value = cart[keys[i]].qty;
      form.appendChild(inp);
    }
    submitBtn.disabled = true;
    submitBtn.textContent = 'Placing order…';
  });

  /* ---- expose global handlers for inline onclick ---- */
  window.posAdjust = adjustQty;
  window.posRemove = removeItem;

  render();

  /* Category filter */
  document.getElementById('posCats')?.addEventListener('click', function(e) {
    var btn = e.target.closest('.pos-cat');
    if (!btn) return;
    this.querySelector('.is-active').classList.remove('is-active');
    btn.classList.add('is-active');
    var cat = btn.dataset.cat;
    grid.querySelectorAll('.pos-item').forEach(function(el) {
      el.style.display = !cat || el.dataset.cat === cat ? '' : 'none';
    });
  });
})();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
