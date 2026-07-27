<?php
/**
 * admin/products.php — Product CRUD (menu items).
 */
require_once __DIR__ . '/../init.php';
require_admin();
use App\Models\Product;

$editId   = isset($_GET['edit']) ? (string) $_GET['edit'] : '';
$editing  = null;

if ($editId !== '') {
    $editing = Product::find($editId);
    if (!$editing) {
        flash('Product not found.', 'warn');
        redirect('/admin/products.php');
    }
}

/* ---------- POST handling ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = (string) post('action', '');

    if ($action === 'delete') {
        $id = (string) post('id', '');
        try {
            $product = Product::find($id);
            if ($product) $product->delete();
            flash('Product deleted.', 'ok');
        } catch (Throwable $ex) {
            flash('Could not delete product: ' . $ex->getMessage(), 'danger');
        }
        redirect('/admin/products.php');
    }

    if ($action === 'toggle') {
        $id = (string) post('id', '');
        $product = Product::find($id);
        if ($product) {
            $current = $product->status ?? 'available';
            $newStatus = $current === 'available' ? 'unavailable' : 'available';
            $product->update(['status' => $newStatus]);
            flash('Product is now ' . $newStatus . '.', 'ok');
        }
        redirect('/admin/products.php');
    }

    if ($action === 'save') {
        $id          = (string) post('id', '');
        $name        = trim((string) post('name', ''));
        $category    = trim((string) post('category', ''));
        $description = trim((string) post('description', ''));
        $price       = (float) post('price', 0);
        $stock       = (int) post('stock', 0);

        if ($name === '' || $category === '' || $price < 0) {
            flash('Name, category and a valid price are required.', 'danger');
            if ($id) redirect('/admin/products.php?edit=' . urlencode($id));
            redirect('/admin/products.php');
        }

        $data = [
            'name'        => $name,
            'category'    => $category,
            'description' => $description,
            'price'       => $price,
            'stock'       => $stock,
            'updated_at'  => now(),
        ];

        try {
            $b64 = upload_to_base64('image', UPLOAD_ROOT . '/admin/item');
            if ($b64 !== null) {
                $data['image'] = $b64;
            }
            // Client-side canvas crop (no GD dependency)
            $cropped = post('cropped');
            if ($cropped) {
                $parts = explode(',', $cropped, 2);
                $raw = base64_decode($parts[1] ?? $parts[0] ?? '', true);
                if ($raw !== false && strlen($raw) > 0) {
                    $filename = bin2hex(random_bytes(16)) . '.jpg';
                    file_put_contents(__DIR__ . '/../assets/img/products/' . $filename, $raw);
                    $data['image'] = 'b64:' . base64_encode($raw);
                }
            }

            if ($id !== '') {
                $product = Product::find($id);
                if ($product) $product->update($data);
                flash('Product updated.', 'ok');
            } else {
                $data['image']      = $data['image']      ?? '';
                $data['created_at'] = now();
                $p = new Product($data);
                $p->save();
                flash('Product created.', 'ok');
            }
        } catch (Throwable $ex) {
            flash('Could not save product: ' . $ex->getMessage(), 'danger');
            if ($id) redirect('/admin/products.php?edit=' . urlencode($id));
            redirect('/admin/products.php');
        }
        redirect('/admin/products.php');
    }
}

/* ---------- List data ---------- */
$page  = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$productPage = Product::paginate($page, $perPage);
$products = $productPage['data'];
$totalProducts = $productPage['total'];
$pages = $productPage['pages'];

// Sort newest first
uasort($products, function ($a, $b) {
    $ta = strtotime((string) ($a['created_at'] ?? '')) ?: 0;
    $tb = strtotime((string) ($b['created_at'] ?? '')) ?: 0;
    return $tb <=> $ta;
});

// Form defaults
$formName        = $editing?->get('name')        ?? post('name', '');
$formCategory    = $editing?->get('category')    ?? post('category', '');
$formDescription = $editing?->get('description') ?? post('description', '');
$formPrice       = $editing?->get('price')       ?? post('price', '');
$formStock       = $editing?->get('stock')       ?? post('stock', '');
$formImage       = $editing?->get('image')       ?? '';

$pageTitle = 'Products';
$activeNav = 'products';
$layout    = 'wide';
require_once __DIR__ . '/../includes/header.php';
?>
<style>
  .thumb--sm { width:48px; height:48px; border-radius:9px; object-fit:cover; border:1px solid var(--line); background:var(--bg-2); }
  .img-preview { width:120px; height:120px; object-fit:contain; border-radius:10px; border:1px solid var(--line); background:var(--bg-2); }
  .img-row { display:flex; align-items:center; gap:14px; }
  .layout-2 { display:grid; grid-template-columns:1.6fr 1fr; gap:24px; align-items:start; }
  @media (max-width:980px) { .layout-2 { grid-template-columns:1fr; } }
  .stock-low { color:var(--danger); font-weight:600; }
  .stock-out { color:var(--muted); }
  .crop-modal { position:fixed; inset:0; z-index:1000; display:flex; align-items:center; justify-content:center; }
  .crop-modal-bg { position:absolute; inset:0; background:rgba(0,0,0,.7); }
  .crop-modal-box { position:relative; background:var(--bg); border-radius:12px; box-shadow:0 4px 24px rgba(0,0,0,.4); width:fit-content; height:fit-content; max-width:90vw; max-height:90vh; padding:20px; }
  .crop-modal-actions { display:flex; gap:8px; justify-content:flex-end; margin-top:12px; }
  .crop-stage { position:relative; border-radius:10px; overflow:hidden; border:1px solid var(--line); background:var(--bg-2); display:inline-block; }
  .crop-stage img { display:block; max-width:calc(90vw - 50px); max-height:calc(90vh - 100px); width:auto; height:auto; }
  .crop-box { position:absolute; width:200px; height:200px; box-shadow:0 0 0 9999px rgba(0,0,0,.5); border:2px solid #fff; cursor:move; border-radius:4px; box-sizing:border-box; }
  @media (max-width:540px) {
    .crop-modal-box { padding:12px; }
    .crop-stage img { max-width:calc(100vw - 40px); max-height:calc(100vh - 90px); }
  }
</style>

<div class="page-head">
  <div class="page-head__row">
    <div>
      <span class="eyebrow">Menu</span>
      <h1 class="mt-2">Products</h1>
      <p>Create, edit and remove dishes on your menu. Items with stock ≤ 5 are flagged as low.</p>
    </div>
    <a class="btn btn--gold" href="#product-form"><?= $editing ? 'Editing product' : 'Add product' ?></a>
  </div>
</div>

<div class="layout-2">
  <!-- List -->
  <div class="card">
    <div class="card__head">
      <div><h2>All products</h2><small><?= $totalProducts ?> item(s) &middot; page <?= $page ?> of <?= $pages ?></small></div>
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
            <th>Item</th><th>Category</th><th class="num">Price</th><th>Status</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$products): ?>
          <tr><td colspan="5" class="muted t-center">No products yet — add your first dish using the form on the right.</td></tr>
        <?php else: foreach ($products as $pid => $p):
            $isAvailable = ($p['status'] ?? 'available') === 'available';
            $img = product_image_url($p['image'] ?? '', $pid, 'products');
        ?>
          <tr>
            <td>
              <div class="img-row">
                <img class="thumb--sm" src="<?= e($img) ?>" alt="" onerror="this.onerror=null;this.src='/assets/img/placeholder.svg'">
                <div>
                  <strong><?= e($p['name'] ?? 'Untitled') ?></strong><br>
                  <small class="micro"><?= mb_substr((string) ($p['description'] ?? ''), 0, 60) ?><?= mb_strlen((string) ($p['description'] ?? '')) > 60 ? '…' : '' ?></small>
                </div>
              </div>
            </td>
            <td><?= e($p['category'] ?? '—') ?></td>
            <td class="num"><?= e(money((float) ($p['price'] ?? 0))) ?></td>
            <td>
              <form method="post" action="/admin/products.php" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= e((string) $pid) ?>">
                <button class="btn btn--sm <?= $isAvailable ? 'btn--ok' : 'btn--muted' ?>" type="submit"
                        title="Click to <?= $isAvailable ? 'disable' : 'enable' ?> this product">
                  <?= $isAvailable ? '● Available' : '○ Not Available' ?>
                </button>
              </form>
            </td>
            <td>
              <div class="row" style="flex-wrap:nowrap">
                <a class="btn btn--ghost btn--sm" href="/admin/products.php?edit=<?= urlencode((string) $pid) ?>">Edit</a>
                <form method="post" action="/admin/products.php" style="display:inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= e((string) $pid) ?>">
                  <button class="btn btn--danger btn--sm" type="submit"
                          data-confirm="Delete product '<?= e($p['name'] ?? '') ?>'? This cannot be undone.">Delete</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Form -->
  <div class="card" id="product-form">
    <div class="card__head">
      <div><h2><?= $editing ? 'Edit product' : 'Add product' ?></h2></div>
      <?php if ($editing): ?>
        <a class="btn btn--ghost btn--sm" href="/admin/products.php">Cancel edit</a>
      <?php endif; ?>
    </div>
    <div class="card__body">
      <form method="post" action="/admin/products.php" enctype="multipart/form-data" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= e($editId) ?>">

        <div class="field">
          <label for="name">Name</label>
          <input class="input" type="text" id="name" name="name" required value="<?= e($formName) ?>" placeholder="House Signature Plate">
        </div>

        <div class="form-grid form-grid--2">
          <div class="field">
            <label for="category">Category</label>
            <input class="input" type="text" id="category" name="category" required list="cat-list" value="<?= e($formCategory) ?>" placeholder="Mains">
            <datalist id="cat-list">
              <option value="Mains"><option value="Starters"><option value="Desserts"><option value="Drinks"><option value="Sides">
            </datalist>
          </div>
          <div class="field">
            <label for="price">Price (₱)</label>
            <input class="input" type="number" id="price" name="price" required min="0" step="0.01" value="<?= e($formPrice) ?>" placeholder="0.00">
          </div>
        </div>

        <div class="field">
          <label for="description">Description</label>
          <textarea class="textarea" id="description" name="description" placeholder="Slow-braised beef in peanut sauce…"><?= e($formDescription) ?></textarea>
        </div>

        <div class="field">
          <label for="image">Image</label>
          <div class="img-row">
            <img id="image-preview" class="img-preview"
                 src="<?= $formImage ? e(image_display_src($formImage)) : e('/assets/img/placeholder.svg') ?>"
                 alt="Preview">
            <div>
              <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/gif,image/webp">
              <span class="hint">JPG, PNG or WebP · max 5MB<?= $formImage ? ' · leave blank to keep current' : '' ?></span>
            </div>
          </div>
        </div>

        <div class="form-actions">
          <button class="btn btn--gold" type="submit"><?= $editing ? 'Save changes' : 'Create product' ?></button>
          <?php if ($editing): ?>
            <a class="btn btn--ghost" href="/admin/products.php">Cancel</a>
          <?php endif; ?>
        </div>
      </form>

      <!-- Crop Modal -->
      <div id="crop-modal" class="crop-modal" style="display:none">
        <div class="crop-modal-bg"></div>
        <div class="crop-modal-box">
          <div class="crop-stage" id="crop-stage">
            <img id="crop-img" src="" alt="Crop">
            <div id="crop-box" class="crop-box"></div>
          </div>
          <div class="crop-modal-actions">
            <button type="button" id="crop-apply" class="btn btn-primary">Apply</button>
            <button type="button" id="crop-cancel" class="btn">Cancel</button>
          </div>
        </div>
      </div>
      <!-- /Crop Modal -->

    </div>
  </div>
</div>

<script>
(function(){
  var inp = document.getElementById('image'), prev = document.getElementById('image-preview');
  var box = document.getElementById('crop-box'), stage = document.getElementById('crop-stage');
  var img = document.getElementById('crop-img'), modal = document.getElementById('crop-modal');
  if (!inp || !modal || !stage || !img || !box) return;

  var dn = false, mode = null, dir = null, start = {}, croppedDataUrl = null;
  var MIN = 30, EDGE = 10, BS = parseInt(getComputedStyle(stage).borderTopWidth) || 1;

  // Edge detection for resize handles (8 directions)
  function getDir(e) {
    var r = box.getBoundingClientRect();
    var x = e.clientX - r.left, y = e.clientY - r.top;
    var l = x < EDGE, r2 = x > r.width - EDGE, t = y < EDGE, b = y > r.height - EDGE;
    if (l && t) return 'nw'; if (r2 && t) return 'ne';
    if (l && b) return 'sw'; if (r2 && b) return 'se';
    if (t) return 'n'; if (b) return 's';
    if (l) return 'w'; if (r2) return 'e';
    return null;
  }

  // File selected → open crop modal
  inp.addEventListener('change', function() {
    var f = this.files && this.files[0];
    if (!f) return;
    croppedDataUrl = null;
    modal.style.display = '';
    img.onload = function() {
      var dw = img.offsetWidth, dh = img.offsetHeight;
      var bs = Math.min(dw, dh, 200) | 0;
      box.style.width = bs + 'px'; box.style.height = bs + 'px';
      box.style.left = ((dw - bs) / 2) + 'px'; box.style.top = ((dh - bs) / 2) + 'px';
    };
    prev.src = img.src = URL.createObjectURL(f);
  });

  // Apply: crop on canvas, store result, close modal
  document.getElementById('crop-apply').addEventListener('click', function() {
    var dw = img.offsetWidth, dh = img.offsetHeight;
    var sx = img.naturalWidth / dw, sy = img.naturalHeight / dh;
    var l = parseInt(box.style.left) || 0, t = parseInt(box.style.top) || 0;
    var bw = box.offsetWidth || 1, bh = box.offsetHeight || 1;
    var cw = Math.round(bw * sx), ch = Math.round(bh * sy);
    var c = document.createElement('canvas');
    c.width = cw; c.height = ch;
    c.getContext('2d').drawImage(img, l * sx, t * sy, cw, ch, 0, 0, cw, ch);
    croppedDataUrl = c.toDataURL('image/jpeg', 0.85);
    prev.src = croppedDataUrl;
    modal.style.display = 'none';
  });

  // Cancel: close modal, clear file input
  document.getElementById('crop-cancel').addEventListener('click', function() {
    modal.style.display = 'none';
    inp.value = '';
    prev.removeAttribute('src');
  });

  // pointer handlers (mouse + touch)
  function ptrDown(e) {
    if (e.button) return;
    var p = e.touches ? e.touches[0] : e;
    dn = true; dir = getDir(p); mode = dir ? 'resize' : 'move';
    var r = box.getBoundingClientRect(), pr = stage.getBoundingClientRect();
    start = { mx: p.clientX, my: p.clientY, ox: r.left - pr.left, oy: r.top - pr.top, ow: r.width, oh: r.height };
    e.preventDefault();
  }
  box.addEventListener('mousedown', ptrDown);
  box.addEventListener('touchstart', ptrDown, { passive: false });

  function ptrMove(e) {
    var p = e.touches ? e.touches[0] : e;
    if (!dn) {
      var d = getDir(p);
      if (d) {
        var cs = { n:'ns', s:'ns', e:'ew', w:'ew', ne:'nesw', sw:'nesw', nw:'nwse', se:'nwse' };
        box.style.cursor = cs[d] + '-resize';
      } else { box.style.cursor = 'move'; }
      return;
    }
    e.preventDefault();
    var dx = p.clientX - start.mx, dy = p.clientY - start.my;
    var x = start.ox, y = start.oy, w = start.ow, h = start.oh;
    var dw = img.offsetWidth, dh = img.offsetHeight;
    if (mode === 'move') {
      x = start.ox + dx;
      y = start.oy + dy;
    } else {
      if (dir.includes('e')) w = Math.max(MIN, Math.min(start.ow + dx, dw - start.ox));
      if (dir.includes('s')) h = Math.max(MIN, Math.min(start.oh + dy, dh - start.oy));
      if (dir.includes('w')) {
        var nx = Math.max(0, Math.min(start.ox + dx, start.ox + start.ow - MIN));
        w = start.ow + (start.ox - nx);
        x = nx;
      }
      if (dir.includes('n')) {
        var ny = Math.max(0, Math.min(start.oy + dy, start.oy + start.oh - MIN));
        h = start.oh + (start.oy - ny);
        y = ny;
      }
      // Enforce square
      if (w !== start.ow) h = w; else if (h !== start.oh) w = h;
      var s = Math.min(Math.max(w, h, MIN), Math.min(dw, dh));
      w = h = s;
    }
    // Clamp box to image bounds
    var ir = img.getBoundingClientRect(), sr = stage.getBoundingClientRect();
    var ix = ir.left - sr.left - BS, iy = ir.top - sr.top - BS;
    var iw = img.offsetWidth, ih = img.offsetHeight;
    x = Math.max(ix, Math.min(x, ix + iw - w));
    y = Math.max(iy, Math.min(y, iy + ih - h));
    box.style.left = x + 'px'; box.style.top = y + 'px';
    box.style.width = w + 'px'; box.style.height = h + 'px';
  }
  document.addEventListener('mousemove', ptrMove);
  document.addEventListener('touchmove', ptrMove, { passive: false });

  function ptrEnd() { dn = false; mode = null; dir = null; }
  document.addEventListener('mouseup', ptrEnd);
  document.addEventListener('touchend', ptrEnd);

  // On submit: embed cropped image if available
  inp.closest('form').addEventListener('submit', function() {
    if (croppedDataUrl) {
      var h = document.createElement('input');
      h.type = 'hidden'; h.name = 'cropped'; h.value = croppedDataUrl;
      this.appendChild(h);
    }
  });
})();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
