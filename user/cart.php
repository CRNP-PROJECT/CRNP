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
    $action = post('action');
    $cart   = get_cart();
    $id     = post('item_id');

    if ($action === 'update' && $id !== '' && isset($cart[$id])) {
        $stock = max(1, (int) ($cart[$id]['stock'] ?? 1));
        $cur   = (int) ($cart[$id]['qty'] ?? 1);
        $delta = post('delta', '');
        $typed = (int) post('qty', $cur);

        if ($delta !== '') {
            $base  = $typed;              // input reflects what user sees
            $newQ  = $base + (int) $delta;
        } else {
            $newQ  = $typed;              // user pressed Enter / Update
        }
        $newQ = max(1, min($stock, $newQ));

        $cart[$id]['qty'] = $newQ;
        set_cart($cart);
        flash('Quantity updated.', 'ok');
        redirect('/user/cart.php');
    }

    if ($action === 'remove' && $id !== '' && isset($cart[$id])) {
        unset($cart[$id]);
        set_cart($cart);
        flash('Item removed from your cart.', 'ok');
        redirect('/user/cart.php');
    }

    flash('Could not process that action.', 'warn');
    redirect('/user/cart.php');
}

$cart = get_cart();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
  <span class="eyebrow">Checkout · Step 1 of 2</span>
  <h1>Your cart</h1>
  <p>Review your selection before heading to checkout.</p>
</div>

<?php if (!$cart): ?>
  <div class="empty">
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
  <div class="card card--pad">
    <?php foreach ($cart as $id => $item):
      $unit     = (float) ($item['price'] ?? 0);
      $qty      = (int)   ($item['qty']   ?? 1);
      $subtotal = $unit * $qty;
      $stock    = max(1, (int) ($item['stock'] ?? 1));
      $img      = image_display_src($item['image'] ?? '');
    ?>
      <div class="cart-item">
        <img class="cart-item__media" src="<?= e($img) ?>" alt="" loading="lazy">

        <div>
          <div class="cart-item__name"><?= e($item['name'] ?? 'Item') ?></div>
          <div class="cart-item__price"><?= money($unit) ?> each</div>
          <form method="post" class="qty mt-2" aria-label="Quantity for <?= e($item['name'] ?? 'item') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action"  value="update">
            <input type="hidden" name="item_id" value="<?= e($id) ?>">
            <button type="submit" name="delta" value="-1" aria-label="Decrease quantity">&minus;</button>
            <input type="number" name="qty" value="<?= $qty ?>" min="1" max="<?= $stock ?>" inputmode="numeric" aria-label="Quantity">
            <button type="submit" name="delta" value="1" aria-label="Increase quantity">+</button>
          </form>
        </div>

        <div class="t-right">
          <div class="product__price"><?= money($subtotal) ?></div>
          <form method="post" class="mt-2">
            <?= csrf_field() ?>
            <input type="hidden" name="action"  value="remove">
            <input type="hidden" name="item_id" value="<?= e($id) ?>">
            <button class="btn btn--ghost btn--sm" type="submit" data-confirm="Remove this item from your cart?">Remove</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>

    <hr class="divider">

    <div class="row row--between">
      <div>
        <div class="muted" style="font-size:13px;letter-spacing:.06em;text-transform:uppercase">Order total</div>
        <div style="font-family:var(--serif);font-size:1.7rem;font-weight:700;line-height:1.1"><?= money(cart_total()) ?></div>
      </div>
      <div class="row">
        <a class="btn btn--ghost" href="/user/products.php">Keep shopping</a>
        <a class="btn btn--gold btn--lg" href="/user/checkout.php">Proceed to checkout</a>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
