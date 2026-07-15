<?php
/**
 * admin/products.php — Product CRUD (menu items).
 */
require_once __DIR__ . '/../init.php';
require_admin();

$db       = getDB();
$editId   = isset($_GET['edit']) ? (string) $_GET['edit'] : '';
$editing  = null;

if ($editId !== '') {
    $editing = $db->retrieve('/products/' . $editId);
    if (!is_array($editing)) {
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
            $db->delete('/products', $id);
            flash('Product deleted.', 'ok');
        } catch (Throwable $ex) {
            flash('Could not delete product: ' . $ex->getMessage(), 'danger');
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
            // Image upload (optional)
            $filename = save_upload('image', UPLOAD_ROOT . '/admin/item');
            if ($filename !== null) {
                $data['image'] = $filename;
            }

            if ($id !== '') {
                $db->update('/products', $id, $data);
                flash('Product updated.', 'ok');
            } else {
                $data['image']      = $data['image']      ?? '';
                $data['created_at'] = now();
                $db->insert('/products', $data);
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
$products = rows($db->retrieve('/products'));
// Sort newest first
uasort($products, function ($a, $b) {
    $ta = strtotime((string) ($a['created_at'] ?? '')) ?: 0;
    $tb = strtotime((string) ($b['created_at'] ?? '')) ?: 0;
    return $tb <=> $ta;
});

// Form defaults
$formName        = $editing['name']        ?? post('name', '');
$formCategory    = $editing['category']    ?? post('category', '');
$formDescription = $editing['description'] ?? post('description', '');
$formPrice       = $editing['price']       ?? post('price', '');
$formStock       = $editing['stock']       ?? post('stock', '');
$formImage       = $editing['image']       ?? '';

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
      <div><h2>All products</h2><small><?= count($products) ?> item(s)</small></div>
    </div>
    <div class="table-wrap" style="border:0;border-radius:0;">
      <table class="tbl">
        <thead>
          <tr>
            <th>Item</th><th>Category</th><th class="num">Price</th><th class="num">Stock</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$products): ?>
          <tr><td colspan="5" class="muted t-center">No products yet — add your first dish using the form on the right.</td></tr>
        <?php else: foreach ($products as $pid => $p):
            $stock = (int) ($p['stock'] ?? 0);
            $stockClass = $stock === 0 ? 'stock-out' : ($stock <= 5 ? 'stock-low' : '');
            $img = upload_web('admin/item', $p['image'] ?? '');
        ?>
          <tr>
            <td>
              <div class="img-row">
                <img class="thumb--sm" src="<?= e($img) ?>" alt="">
                <div>
                  <strong><?= e($p['name'] ?? 'Untitled') ?></strong><br>
                  <small class="micro"><?= mb_substr((string) ($p['description'] ?? ''), 0, 60) ?><?= mb_strlen((string) ($p['description'] ?? '')) > 60 ? '…' : '' ?></small>
                </div>
              </div>
            </td>
            <td><?= e($p['category'] ?? '—') ?></td>
            <td class="num"><?= e(money((float) ($p['price'] ?? 0))) ?></td>
            <td class="num <?= $stockClass ?>"><?= $stock ?></td>
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
          <label for="stock">Stock (units)</label>
          <input class="input" type="number" id="stock" name="stock" min="0" step="1" value="<?= e($formStock) ?>" placeholder="0">
          <span class="hint">Items with ≤ 5 units are flagged as low stock.</span>
        </div>

        <div class="field">
          <label for="image">Image</label>
          <div class="img-row">
            <img id="image-preview" class="img-preview"
                 src="<?= $formImage ? e(upload_web('admin/item', $formImage)) : e('/assets/img/placeholder.svg') ?>"
                 alt="Preview">
            <div>
              <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp">
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
    </div>
  </div>
</div>

<script>
  // Image preview before upload
  (function () {
    var input = document.getElementById('image');
    var preview = document.getElementById('image-preview');
    if (!input || !preview) return;
    input.addEventListener('change', function () {
      var f = this.files && this.files[0];
      if (!f) return;
      preview.src = URL.createObjectURL(f);
    });
  })();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
