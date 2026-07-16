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

/* ---------- POST: buy now ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $pid = post('product_id');
    $qty = max(1, (int) post('qty', 1));

    if ($pid === '') {
        flash('Invalid request.', 'danger');
        redirect('/user/products.php');
    }

    $p = Product::find($pid);
    if (!$p) {
        flash('Item not found.', 'danger');
        redirect('/user/products.php');
    }

    $stock = (int) ($p->stock ?? 0);
    if ($stock <= 0) {
        flash('Sorry, "' . ($p['name'] ?? 'that item') . '" is sold out.', 'danger');
        redirect('/user/products.php');
    }

    $cart  = get_cart();
    $cur   = isset($cart[$pid]) ? (int) $cart[$pid]['qty'] : 0;
    $newQty = min($stock, $cur + $qty);

    $cart[$pid] = [
        'id'    => $pid,
        'name'  => $p->name  ?? 'Item',
        'price' => (float) ($p->price ?? 0),
        'qty'   => $newQty,
        'image' => $p->image ?? '',
        'stock' => $stock,
    ];
    set_cart($cart);

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
?>

<style>
  .hero { background-image: linear-gradient(160deg, rgba(42,33,24,.82), rgba(24,18,16,.92)), url('/assets/img/shop-bg.png'); background-size: cover; background-position: center; }
</style>
<style>
  .prod-cats { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:16px; }
  .prod-cat { font-size:12px; font-weight:600; padding:3px 12px; border-radius:999px;
    border:1px solid var(--line,#e6dfd1); background:var(--surface,#fff);
    cursor:pointer; color:var(--muted); transition:.15s; }
  .prod-cat.is-active { background:var(--ink,#211b14); color:#fff; border-color:var(--ink); }
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
    <form method="get" class="input-group" role="search" action="/user/products.php" style="max-width:340px;">
      <input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Search dishes…" aria-label="Search dishes">
      <button class="btn btn--outline btn--sm" type="submit">Search</button>
      <?php if ($q !== ''): ?>
        <a class="btn btn--ghost btn--sm" href="/user/products.php">Clear</a>
      <?php endif; ?>
    </form>
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
<?php if ($cats): ?>
    <div class="prod-cats" id="prodCats">
      <button class="prod-cat is-active" data-cat="">All</button>
      <?php foreach (array_keys($cats) as $c): ?>
        <button class="prod-cat" data-cat="<?= e($c) ?>"><?= e($c) ?></button>
      <?php endforeach; ?>
    </div>
<?php endif; ?>
    <div class="grid grid--products">
      <?php foreach ($products as $id => $p):
        $stock   = (int) ($p['stock'] ?? 0);
        $soldOut = $stock <= 0;
        $low     = !$soldOut && $stock <= 5;
        $desc    = trim($p['description'] ?? $p['short'] ?? '');
        if (mb_strlen($desc) > 130) {
            $desc = mb_substr($desc, 0, 127) . '…';
        }
        $img = image_display_src($p['image'] ?? '');
      ?>
        <article class="product">
          <div class="product__media">
            <img src="<?= e($img) ?>" alt="<?= e($p['name'] ?? 'Dish') ?>" loading="lazy">
            <?php if ($soldOut): ?>
              <span class="badge badge--muted" style="position:absolute;top:10px;left:10px">Sold out</span>
            <?php elseif ($low): ?>
              <span class="badge badge--warn" style="position:absolute;top:10px;left:10px">Low stock</span>
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
              <div>
                <div class="product__price"><?= money($p['price'] ?? 0) ?></div>
                <?php if ($soldOut): ?>
                  <div class="product__stock out">Sold out</div>
                <?php elseif ($low): ?>
                  <div class="product__stock low">Only <?= $stock ?> left</div>
                <?php else: ?>
                  <div class="product__stock">In stock: <?= $stock ?></div>
                <?php endif; ?>
              </div>
              <form method="post" action="/user/products.php">
                <?= csrf_field() ?>
                <input type="hidden" name="product_id" value="<?= e($id) ?>">
                <input type="hidden" name="qty" value="1">
                <button class="btn btn--gold btn--sm" type="submit" <?= $soldOut ? 'disabled' : '' ?>>
                  <?= $soldOut ? 'Sold out' : 'Buy now' ?>
                </button>
              </form>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<script>
document.getElementById('prodCats')?.addEventListener('click', function(e) {
  var btn = e.target.closest('.prod-cat');
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
