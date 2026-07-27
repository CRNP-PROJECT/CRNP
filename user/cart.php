<?php
/**
 * cart.php — Customer shopping cart.
 * Lists cart items with quantity steppers, supports update / remove, and
 * forwards to checkout.
 */
require_once __DIR__ . '/../init.php';
require_user();

$activeNav = 'cart';
$pageTitle = 'Your Cart';
$layout    = 'narrow';

/* ---------- POST: update / remove ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $isAjax = is_ajax_request();
    $action = post('action');
    $cart   = get_cart();
    $id     = post('item_id');

    if ($action === 'update' && $id !== '' && isset($cart[$id])) {
        $stock = max(1, (int) ($cart[$id]['stock'] ?? 1));
        $delta = post('delta', '');
        $typed = (int) post('qty', (int) ($cart[$id]['qty'] ?? 1));

        if ($delta !== '') {
            $newQ = $typed + (int) $delta;
        } else {
            $newQ = $typed;
        }

        // auto-remove when quantity drops to zero or below
        if ($newQ <= 0) {
            unset($cart[$id]);
            set_cart($cart);

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'item_id' => $id,
                    'total'   => money(cart_total()),
                    'count'   => cart_count(),
                    'empty'   => empty($cart),
                    'removed' => true,
                ]);
                exit;
            }

            flash('Item removed from your cart.', 'ok');
            redirect('/user/cart.php');
        }

        $newQ = min($stock, $newQ);

        $cart[$id]['qty'] = $newQ;
        set_cart($cart);

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success'  => true,
                'item_id'  => $id,
                'qty'      => $newQ,
                'subtotal' => money((float) ($cart[$id]['price'] ?? 0) * $newQ),
                'total'    => money(cart_total()),
                'count'    => cart_count(),
            ]);
            exit;
        }

        flash('Quantity updated.', 'ok');
        redirect('/user/cart.php');
    }

    if ($action === 'remove' && $id !== '' && isset($cart[$id])) {
        unset($cart[$id]);
        set_cart($cart);

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'item_id' => $id,
                'total'   => money(cart_total()),
                'count'   => cart_count(),
                'empty'   => empty($cart),
            ]);
            exit;
        }

        flash('Item removed from your cart.', 'ok');
        redirect('/user/cart.php');
    }

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Could not process that action.']);
        exit;
    }

    flash('Could not process that action.', 'warn');
    redirect('/user/cart.php');
}

$cart = get_cart();

foreach ($cart as $id => &$item) {
    if (empty($item['stock'])) {
        try {
            $db   = \App\Core\Model::db();
            $row  = $db->retrieve('/products/' . $id);
            $item['stock'] = is_array($row) ? (int) ($row['stock'] ?? 0) : 0;
        } catch (\Throwable $e) {
            $item['stock'] = 0;
        }
    }
}
unset($item);
set_cart($cart);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
  <span class="eyebrow">Checkout · Step 1 of 2</span>
  <h1>Your cart</h1>
  <p>Review your selection before heading to checkout.</p>
</div>

<?php if (!$cart): ?>
  <div class="empty" id="cartEmpty">
    <div class="empty__icon" aria-hidden="true">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
        <path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>
      </svg>
    </div>
    <h3>Your cart is empty</h3>
    <p>Browse the menu and add a few plates to get started.</p>
    <a class="btn btn--gold mt-2" href="/user/products.php">Browse the menu</a>
  </div>
<?php else: ?>
  <div class="card card--pad" id="cartWrap">
    <div id="cartItems">
    <?php foreach ($cart as $id => $item):
      $unit     = (float) ($item['price'] ?? 0);
      $qty      = (int)   ($item['qty']   ?? 1);
      $subtotal = $unit * $qty;
      $stock    = max(1, (int) ($item['stock'] ?? 1));
      $img      = product_image_url($item['image'] ?? '', $id, 'products');
    ?>
      <div class="cart-item" data-item-id="<?= e($id) ?>" data-unit="<?= $unit ?>" data-stock="<?= $stock ?>">
        <img class="cart-item__media" src="<?= e($img) ?>" alt="" loading="lazy">

        <div>
          <div class="cart-item__name"><?= e($item['name'] ?? 'Item') ?></div>
          <div class="cart-item__price"><?= money($unit) ?> each</div>
          <form method="post" class="qty mt-2" aria-label="Quantity for <?= e($item['name'] ?? 'item') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="item_id" value="<?= e($id) ?>">
            <button type="button" data-delta="-1" aria-label="Decrease quantity">&minus;</button>
            <input type="number" name="qty" value="<?= $qty ?>" min="1" max="<?= $stock ?>" inputmode="numeric" aria-label="Quantity">
            <button type="button" data-delta="1" aria-label="Increase quantity">+</button>
          </form>
        </div>

        <div class="t-right">
          <div class="product__price" data-subtotal><?= money($subtotal) ?></div>
          <form method="post" class="cart-remove-form mt-2">
            <?= csrf_field() ?>
            <input type="hidden" name="action"  value="remove">
            <input type="hidden" name="item_id" value="<?= e($id) ?>">
            <button class="btn btn--ghost btn--sm" type="submit" data-confirm="Remove this item from your cart?">Remove</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
    </div>

    <hr class="divider">

    <div class="row row--between" id="cartFooter">
      <div>
        <div class="muted" style="font-size:13px;letter-spacing:.06em;text-transform:uppercase">Order total</div>
        <div id="cartTotal" style="font-family:var(--sans);font-size:1.7rem;font-weight:700;line-height:1.1"><?= money(cart_total()) ?></div>
      </div>
      <div class="row">
        <a class="btn btn--ghost" href="/user/products.php">Keep shopping</a>
        <a class="btn btn--gold btn--lg" href="/user/checkout.php">Proceed to checkout</a>
      </div>
    </div>
  </div>
<?php endif; ?>

<script>
(function () {
  document.querySelectorAll('form.qty').forEach(function (form) {
    var input = form.querySelector('input[name="qty"]');
    if (!input) return;

    form.querySelectorAll('button[data-delta]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var cur = (parseInt(input.value, 10) || 1) + (parseInt(btn.getAttribute('data-delta'), 10) || 0);
        input.value = Math.max(parseInt(input.min || '1'), Math.min(parseInt(input.max || '99'), cur));
        form.submit();
      });
    });

    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        form.submit();
      }
    });
  });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
