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
  var csrfToken = '<?= csrf_token() ?>';

  function fmtMoney(n) { return '\u20B1' + Number(n).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','); }

  /* ── toast notification ── */
  function showToast(message, type) {
    var old = document.querySelector('.cart-toast');
    if (old) old.remove();
    var toast = document.createElement('div');
    toast.className = 'cart-toast' + (type === 'error' ? ' cart-toast--error' : '');
    var icon = type === 'error'
      ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>'
      : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
    toast.innerHTML = '<span class="cart-toast__icon">' + icon + '</span><span>' + message + '</span>';
    document.body.appendChild(toast);
    requestAnimationFrame(function () {
      requestAnimationFrame(function () { toast.classList.add('is-visible'); });
    });
    setTimeout(function () {
      toast.classList.remove('is-visible');
      setTimeout(function () { toast.remove(); }, 400);
    }, 2500);
  }

  /* ── confirm toast ── */
  function showConfirm(message, onConfirm) {
    var old = document.querySelector('.confirm-toast');
    if (old) old.remove();
    var toast = document.createElement('div');
    toast.className = 'confirm-toast';
    toast.innerHTML =
      '<span class="confirm-toast__msg">' + message + '</span>' +
      '<div class="confirm-toast__actions">' +
        '<button class="confirm-toast__btn confirm-toast__btn--cancel" type="button">Cancel</button>' +
        '<button class="confirm-toast__btn confirm-toast__btn--ok" type="button">Remove</button>' +
      '</div>';
    document.body.appendChild(toast);
    requestAnimationFrame(function () {
      requestAnimationFrame(function () { toast.classList.add('is-visible'); });
    });
    toast.querySelector('.confirm-toast__btn--cancel').addEventListener('click', function () {
      toast.classList.remove('is-visible');
      setTimeout(function () { toast.remove(); }, 300);
    });
    toast.querySelector('.confirm-toast__btn--ok').addEventListener('click', function () {
      toast.classList.remove('is-visible');
      setTimeout(function () { toast.remove(); }, 300);
      onConfirm();
    });
  }

  /* ── AJAX POST helper ── */
  function cartPost(data, callback) {
    var fd = new FormData();
    for (var k in data) fd.append(k, data[k]);
    fd.append('csrf_token', csrfToken);
    fetch('/user/cart.php', {
      method: 'POST', body: fd,
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    })
    .then(function (r) { return r.json(); })
    .then(callback)
    .catch(function () { window.location.reload(); });
  }

  /* ── update footer totals ── */
  function updateFooter(total, count) {
    var totalEl = document.getElementById('cartTotal');
    if (totalEl) totalEl.textContent = total;
    var badge = document.querySelector('.cart-pill .count');
    if (badge) {
      if (count > 0) badge.textContent = count;
      else badge.remove();
    }
  }

  /* ── remove item row ── */
  function removeRow(itemId) {
    var row = document.querySelector('.cart-item[data-item-id="' + itemId + '"]');
    if (row) {
      row.style.transition = 'opacity .25s, transform .25s';
      row.style.opacity = '0';
      row.style.transform = 'translateX(30px)';
      setTimeout(function () { row.remove(); checkEmpty(); }, 260);
    }
  }

  function checkEmpty() {
    var items = document.querySelectorAll('.cart-item');
    if (items.length === 0) {
      var wrap = document.getElementById('cartWrap');
      if (wrap) {
        wrap.outerHTML = '<div class="empty" id="cartEmpty">' +
          '<div class="empty__icon" aria-hidden="true">' +
          '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' +
          '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>' +
          '<path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg></div>' +
          '<h3>Your cart is empty</h3>' +
          '<p>Browse the menu and add a few plates to get started.</p>' +
          '<a class="btn btn--gold mt-2" href="/user/products.php">Browse the menu</a></div>';
      }
    }
  }

  /* ── quantity delta buttons ── */
  document.querySelectorAll('.qty').forEach(function (form) {
    form.querySelectorAll('button[data-delta]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var input = form.querySelector('input[name="qty"]');
        var itemId = form.querySelector('input[name="item_id"]').value;
        var delta = parseInt(btn.getAttribute('data-delta'), 10) || 0;
        var cur = (parseInt(input.value, 10) || 1) + delta;
        var max = parseInt(input.max || '99', 10);
        var min = parseInt(input.min || '1', 10);
        var newQ = Math.max(min, Math.min(max, cur));
        if (newQ === cur) {
          input.value = newQ;
          btn.disabled = true;
          cartPost({ action: 'update', item_id: itemId, qty: newQ }, function (data) {
            btn.disabled = false;
            if (data.success) {
              var row = form.closest('.cart-item');
              if (row && data.subtotal) {
                var sub = row.querySelector('[data-subtotal]');
                if (sub) sub.textContent = data.subtotal;
              }
              if (data.total) updateFooter(data.total, data.count || 0);
              if (data.removed) {
                removeRow(itemId);
                showToast('Item removed from your cart.', 'error');
              }
            }
          });
        }
      });
    });

    var input = form.querySelector('input[name="qty"]');
    if (input) {
      input.addEventListener('change', function () {
        var itemId = form.querySelector('input[name="item_id"]').value;
        var newQ = Math.max(parseInt(input.min || '1', 10), parseInt(input.value, 10) || 1);
        input.value = newQ;
        cartPost({ action: 'update', item_id: itemId, qty: newQ }, function (data) {
          if (data.success) {
            var row = form.closest('.cart-item');
            if (row && data.subtotal) {
              var sub = row.querySelector('[data-subtotal]');
              if (sub) sub.textContent = data.subtotal;
            }
            if (data.total) updateFooter(data.total, data.count || 0);
            if (data.removed) {
              removeRow(itemId);
              showToast('Item removed from your cart.', 'error');
            }
          }
        });
      });
      input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); input.blur(); }
      });
    }
  });

  /* ── remove button → confirm toast → AJAX ── */
  document.querySelectorAll('.cart-remove-form').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var itemId = form.querySelector('input[name="item_id"]').value;
      var row = form.closest('.cart-item');
      var name = row ? (row.querySelector('.cart-item__name')?.textContent || 'this item') : 'this item';
      showConfirm('Remove ' + name + ' from your cart?', function () {
        cartPost({ action: 'remove', item_id: itemId }, function (data) {
          if (data.success) {
            removeRow(itemId);
            if (data.total) updateFooter(data.total, data.count || 0);
            showToast('Item removed from your cart.', 'error');
          }
        });
      });
    });
  });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
