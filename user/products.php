<?php
/**
 * products.php — Customer shop.
 * Lists all products with a hero banner, server-side search, and a Buy-Now
 * action that adds to the session cart.
 */
require_once __DIR__ . '/../init.php';
require_user();
use App\Models\Product;

$activeNav = 'shop';
$pageTitle = 'Shop the Menu';
$layout    = 'wide';

/* ---------- POST: add to cart / buy now ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $pid    = post('product_id');
    $qty    = max(1, (int) post('qty', 1));
    $action = post('cart_action', 'add');

    if ($pid === '') {
        flash('Invalid request.', 'danger');
        redirect('/user/products.php');
    }

    $p = Product::find($pid);
    if (!$p) {
        flash('Item not found.', 'danger');
        redirect('/user/products.php');
    }

    if (($p->status ?? 'available') !== 'available') {
        flash('Sorry, "' . ($p->name ?? 'that item') . '" is not available.', 'danger');
        redirect('/user/products.php');
    }

    $cart  = get_cart();
    $cur   = isset($cart[$pid]) ? (int) $cart[$pid]['qty'] : 0;
    $newQty = $cur + $qty;

    $cart[$pid] = [
        'id'    => $pid,
        'name'  => $p->name  ?? 'Item',
        'price' => (float) ($p->price ?? 0),
        'qty'   => $newQty,
        'image' => $p->image ?? '',
        'stock' => (int) ($p->stock ?? 0),
    ];
    set_cart($cart);

    if ($action === 'buy_now') {
        flash('Added "' . ($p->name ?? 'item') . '" to your cart.', 'ok');
        redirect('/user/checkout.php');
    }

    if (is_ajax_request()) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'count'   => cart_count(),
            'qty'     => $newQty,
            'message' => $cur > 0
                ? 'Quantity updated to ' . $newQty . ' in your cart.'
                : 'Item added to cart.',
        ]);
        exit;
    }

    flash('Added "' . ($p->name ?? 'item') . '" to your cart.', 'ok');
    redirect('/user/cart.php');
}

/* ---------- GET: list + search ---------- */
$q         = trim($_GET['q'] ?? '');
$products  = Product::raw();
$cats = [];
foreach ($products as $p) {
    if (!empty($p['category'])) $cats[$p['category']] = true;
}
ksort($cats);
if ($q !== '') {
    $products = filter_like($products, 'name', $q);
}

require_once __DIR__ . '/../includes/header.php';

function shop_cat_icon(string $cat): string {
    $icons = [
        'starters'  => '<path d="M11 20A7 7 0 0 1 9.8 6.9C15.5 4.9 17 3.5 19 2c1 2 2 4.5 2 8 0 5.5-3.8 10-10 10Z"/><path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"/>',
        'mains'     => '<path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3zm0 0v7"/>',
        'drinks'    => '<path d="M17 8h1a4 4 0 1 1 0 8h-1"/><path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V8z"/><line x1="6" y1="2" x2="6" y2="4"/><line x1="10" y1="2" x2="10" y2="4"/><line x1="14" y1="2" x2="14" y2="4"/>',
        'desserts'  => '<path d="M20 21v-8a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8"/><path d="M4 16s.5-1 2-1 2.5 2 4 2 2.5-2 4-2 2.5 2 4 2 2-1 2-1"/><path d="M2 21h20"/><path d="M7 8v3"/><path d="M12 8v3"/><path d="M17 8v3"/>',
    ];
    return $icons[strtolower(trim($cat))] ?? '<path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3zm0 0v7"/>';
}
?>

<style>
  .hero { background-image: linear-gradient(160deg, rgba(42,33,24,.82), rgba(24,18,16,.92)), url('/assets/img/shop-bg.png'); background-size: cover; background-position: center; }

  /* ── shop toolbar ── */
  .shop-toolbar{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:28px;flex-wrap:wrap}
  .shop-cats{display:flex;gap:8px;flex-wrap:wrap}
  .shop-cat{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;font-size:13px;font-weight:600;font-family:var(--sans);border-radius:999px;border:1px solid var(--line);background:transparent;color:var(--muted);cursor:pointer;transition:all .2s ease;white-space:nowrap;min-height:40px;line-height:1.2}
  .shop-cat__icon{width:16px;height:16px;flex-shrink:0}
  .shop-cat:hover{border-color:var(--gold);color:var(--gold);background:rgba(192,138,46,.08)}
  .shop-cat.is-active{background:var(--gold);color:#fff;border-color:var(--gold)}
  .shop-cat.is-active:hover{background:var(--gold-600);border-color:var(--gold-600);color:#fff}
  .shop-search{display:flex;align-items:center;gap:8px;flex-shrink:0}
  .shop-search__wrap{position:relative;display:flex;align-items:center}
  .shop-search__icon{position:absolute;left:12px;width:16px;height:16px;color:var(--muted);pointer-events:none}
  .shop-search__input{width:220px;font:inherit;font-size:13px;color:var(--ink);background:var(--surface);border:1px solid var(--line);border-radius:999px;padding:8px 14px 8px 36px;min-height:40px;transition:border-color .2s,box-shadow .2s}
  .shop-search__input::placeholder{color:var(--placeholder)}
  .shop-search__input:focus{outline:none;border-color:var(--gold);box-shadow:var(--ring)}

  /* ── product grid refinements ── */
  .grid--products{row-gap:20px}
  .grid--products .product:hover{transform:translateY(-4px);box-shadow:var(--shadow),0 0 28px -6px rgba(192,138,46,.18);border-color:var(--gold)}

  /* ── product card refinements ── */
  .product__badge{position:absolute;top:12px;left:12px;z-index:1}
  .product__desc{font-size:13px;color:var(--muted);line-height:1.55;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;margin:0}
  .product__price{font-family:var(--sans);font-size:1.15rem;color:var(--ink);font-weight:700;letter-spacing:-.01em}
  .product__foot{margin-top:auto;display:flex;flex-direction:column;align-items:flex-start;gap:10px;padding-top:8px}
  .product__actions{display:flex;gap:8px}

  /* ── product action buttons ── */
  .product__btn{display:inline-flex;align-items:center;justify-content:center;gap:5px;padding:8px 12px;font-size:12px;font-weight:600;font-family:var(--sans);border-radius:var(--radius-sm);cursor:pointer;transition:all .2s ease;min-height:36px;flex:1;min-width:0;white-space:nowrap;text-decoration:none;line-height:1.2}
  .product__btn svg{width:14px;height:14px;flex-shrink:0}
  .product__btn--cart{background:transparent;border:1px solid var(--line);color:var(--ink)}
  .product__btn--cart:hover{border-color:var(--gold);color:var(--gold);background:rgba(192,138,46,.08);transform:translateY(-1px);box-shadow:0 2px 8px rgba(192,138,46,.12)}
  .product__btn--cart:active{transform:translateY(0) scale(.97)}
  .product__btn--cart:focus-visible{outline:none;box-shadow:var(--ring)}
  .product__btn--buy{background:var(--gold);border:1px solid var(--gold);color:#fff}
  .product__btn--buy:hover{background:var(--gold-600);border-color:var(--gold-600);transform:translateY(-1px);box-shadow:0 4px 16px rgba(192,138,46,.3)}
  .product__btn--buy:active{transform:translateY(0) scale(.97)}
  .product__btn--buy:focus-visible{outline:none;box-shadow:var(--ring)}
  .product__btn--disabled{background:var(--surface-2);border:1px solid var(--line);color:var(--muted);cursor:not-allowed;opacity:.6;flex:1}

  @media (max-width:639px){
    .shop-toolbar{flex-direction:column;align-items:stretch}
    .shop-cats{overflow-x:auto;flex-wrap:nowrap;padding-bottom:4px;-webkit-overflow-scrolling:touch;scrollbar-width:none}
    .shop-cats::-webkit-scrollbar{display:none}
    .shop-search{width:100%}
    .shop-search__input{width:100%}
  }
  @media (min-width:640px){
    .shop-search__input{width:240px}
  }
</style>

<div class="hero">
  <span class="eyebrow">Today's table</span>
  <h1>Crates of flavor, plated with care.</h1>
  <p>Hand-cut meats, market vegetables, and a cellar of small-batch spices &mdash; composed into plates worth lingering over.</p>
  <div class="hero__actions">
    <a class="btn btn--gold" href="#menu">Browse the menu</a>
    <a class="btn btn--outline" href="/user/booking.php" style="border-color:rgba(244,231,200,.4);color:#f4e7c8">Rent tableware</a>
  </div>
</div>

<div class="page-head mt-6">
  <div class="page-head__row">
    <div>
      <span class="eyebrow">The menu</span>
      <h1><?= $q !== '' ? 'Results for "' . e($q) . '"' : 'All dishes' ?></h1>
      <p><?= count($products) ?> item<?= count($products) === 1 ? '' : 's' ?><?= $q !== '' ? ' matched your search.' : ' crafted fresh, served with intention.' ?></p>
    </div>
  </div>
</div>

<section id="menu">
  <?php if (!$products): ?>
    <div class="empty">
      <div class="empty__icon" aria-hidden="true">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>
        </svg>
      </div>
      <h3>No dishes found</h3>
      <p><?= $q !== '' ? 'Try a different search term, or browse the full menu.' : 'The kitchen is resting. Please check back soon.' ?></p>
      <?php if ($q !== ''): ?>
        <a class="btn btn--gold mt-2" href="/user/products.php">Show full menu</a>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="shop-toolbar">
      <?php if ($cats): ?>
      <div class="shop-cats" id="prodCats">
        <button class="shop-cat is-active" data-cat="">
          <svg class="shop-cat__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
          <span>All</span>
        </button>
        <?php foreach (array_keys($cats) as $c): ?>
        <button class="shop-cat" data-cat="<?= e($c) ?>">
          <svg class="shop-cat__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><?= shop_cat_icon($c) ?></svg>
          <span><?= e($c) ?></span>
        </button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <form method="get" class="shop-search" role="search" action="/user/products.php">
        <div class="shop-search__wrap">
          <svg class="shop-search__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <input class="shop-search__input" type="search" name="q" value="<?= e($q) ?>" placeholder="Search menu…" aria-label="Search dishes">
        </div>
        <?php if ($q !== ''): ?>
          <a class="btn btn--ghost btn--sm" href="/user/products.php">Clear</a>
        <?php endif; ?>
      </form>
    </div>

    <div class="grid grid--products">
      <?php foreach ($products as $id => $p):
        $isAvailable = ($p['status'] ?? 'available') === 'available';
        $desc    = trim($p['description'] ?? $p['short'] ?? '');
        if (mb_strlen($desc) > 130) {
            $desc = mb_substr($desc, 0, 127) . '…';
        }
        $img = product_image_url($p['image'] ?? '', $id, 'products');
      ?>
        <article class="product" style="<?= !$isAvailable ? 'opacity:.55;' : '' ?>">
          <div class="product__media">
            <img src="<?= e($img) ?>" alt="<?= e($p['name'] ?? 'Dish') ?>" loading="lazy" onerror="this.onerror=null;this.src='/assets/img/placeholder.svg'">
            <?php if (!$isAvailable): ?>
              <span class="badge badge--muted product__badge">Not Available</span>
            <?php endif; ?>
          </div>
          <div class="product__body">
            <?php if (!empty($p['category'])): ?>
              <span class="product__cat"><?= e($p['category']) ?></span>
            <?php endif; ?>
            <h3 class="product__name"><?= e($p['name'] ?? 'Untitled') ?></h3>
            <?php if ($desc !== ''): ?>
              <p class="product__desc"><?= e($desc) ?></p>
            <?php endif; ?>
            <div class="product__foot">
              <div class="product__price"><?= money($p['price'] ?? 0) ?></div>
              <?php if ($isAvailable): ?>
              <div class="product__actions">
                <form method="post" action="/user/products.php" class="ajax-add-to-cart" style="display:inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="product_id" value="<?= e($id) ?>">
                  <input type="hidden" name="qty" value="1">
                  <input type="hidden" name="cart_action" value="add">
                  <button class="product__btn product__btn--cart" type="submit">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
                    Add to Cart
                  </button>
                </form>
                <form method="post" action="/user/products.php" style="display:inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="product_id" value="<?= e($id) ?>">
                  <input type="hidden" name="qty" value="1">
                  <input type="hidden" name="cart_action" value="buy_now">
                  <button class="product__btn product__btn--buy" type="submit">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                    Buy Now
                  </button>
                </form>
              </div>
              <?php else: ?>
              <div class="product__actions">
                <button class="product__btn product__btn--disabled" type="button" disabled>Not Available</button>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<script>
document.getElementById('prodCats')?.addEventListener('click', function(e) {
  var btn = e.target.closest('.shop-cat');
  if (!btn) return;
  this.querySelector('.is-active').classList.remove('is-active');
  btn.classList.add('is-active');
  var cat = btn.dataset.cat;
  document.querySelectorAll('.grid--products .product').forEach(function(el) {
    var pc = el.querySelector('.product__cat');
    el.style.display = !cat || (pc && pc.textContent.trim() === cat) ? '' : 'none';
  });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
