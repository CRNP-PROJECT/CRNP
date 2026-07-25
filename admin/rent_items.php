<?php
/**
 * admin/rent_items.php — Rent inventory CRUD.
 */
require_once __DIR__ . '/../init.php';
require_admin();
use App\Models\RentItem;

$editId  = isset($_GET['edit']) ? (string) $_GET['edit'] : '';
$editing = null;

if ($editId !== '') {
    $editing = RentItem::find($editId);
    if (!$editing) {
        flash('Rent item not found.', 'warn');
        redirect('/admin/rent_items.php');
    }
}

/* ---------- POST handling ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = (string) post('action', '');

    if ($action === 'delete') {
        $id = (string) post('id', '');
        try {
            $item = RentItem::find($id);
            if ($item) $item->delete();
            flash('Rent item deleted.', 'ok');
        } catch (Throwable $ex) {
            flash('Could not delete rent item: ' . $ex->getMessage(), 'danger');
        }
        redirect('/admin/rent_items.php');
    }

    if ($action === 'save') {
        $id           = (string) post('id', '');
        $name         = trim((string) post('name', ''));
        $displayName  = trim((string) post('display_name', ''));
        $price        = (float) post('price', 0);
        $quantity     = (int) post('quantity', 0);

        if ($name === '' || $price < 0) {
            flash('Name and a valid price are required.', 'danger');
            if ($id) redirect('/admin/rent_items.php?edit=' . urlencode($id));
            redirect('/admin/rent_items.php');
        }

        $data = [
            'name'         => $name,
            'display_name' => $displayName,
            'price'        => $price,
            'quantity'     => $quantity,
            'updated_at'   => now(),
        ];

        try {
            $b64 = upload_to_base64('image', UPLOAD_ROOT . '/admin/item');
            if ($b64 !== null) {
                $data['image'] = $b64;
            }

            if ($id !== '') {
                $item = RentItem::find($id);
                if ($item) $item->update($data);
                flash('Rent item updated.', 'ok');
            } else {
                $data['image']      = $data['image']      ?? '';
                $data['created_at'] = now();
                $r = new RentItem($data);
                $r->save();
                flash('Rent item created.', 'ok');
            }
        } catch (Throwable $ex) {
            flash('Could not save rent item: ' . $ex->getMessage(), 'danger');
            if ($id) redirect('/admin/rent_items.php?edit=' . urlencode($id));
            redirect('/admin/rent_items.php');
        }
        redirect('/admin/rent_items.php');
    }
}

/* ---------- List ---------- */
$rentItems = RentItem::raw();
uasort($rentItems, function ($a, $b) {
    $ta = strtotime((string) ($a['created_at'] ?? '')) ?: 0;
    $tb = strtotime((string) ($b['created_at'] ?? '')) ?: 0;
    return $tb <=> $ta;
});

$formName        = $editing?->get('name')         ?? post('name', '');
$formDisplayName = $editing?->get('display_name') ?? post('display_name', '');
$formPrice       = $editing?->get('price')        ?? post('price', '');
$formQuantity    = $editing?->get('quantity')     ?? post('quantity', '');
$formImage       = $editing?->get('image')        ?? '';

$pageTitle = 'Rent Items';
$activeNav = 'rent';
$layout    = 'wide';
require_once __DIR__ . '/../includes/header.php';
?>
<style>
  .thumb--sm { width:48px; height:48px; border-radius:9px; object-fit:cover; border:1px solid var(--line); background:var(--bg-2); }
  .img-preview { width:120px; height:120px; object-fit:cover; border-radius:10px; border:1px solid var(--line); background:var(--bg-2); }
  .img-row { display:flex; align-items:center; gap:14px; }
  .layout-2 { display:grid; grid-template-columns:1.6fr 1fr; gap:24px; align-items:start; }
  @media (max-width:980px) { .layout-2 { grid-template-columns:1fr; } }
  .qty-low { color:var(--danger); font-weight:600; }
  .qty-out { color:var(--muted); }
</style>

<div class="page-head">
  <div class="page-head__row">
    <div>
      <span class="eyebrow">Inventory</span>
      <h1 class="mt-2">Rent items</h1>
      <p>Manage items available for reservation. Quantity is the on-hand stock for rent.</p>
    </div>
    <a class="btn btn--gold" href="#rent-form"><?= $editing ? 'Editing item' : 'Add item' ?></a>
  </div>
</div>

<div class="layout-2">
  <!-- List -->
  <div class="card">
    <div class="card__head">
      <div><h2>All rent items</h2><small><?= count($rentItems) ?> item(s)</small></div>
    </div>
    <div class="table-wrap" style="border:0;border-radius:0;">
      <table class="tbl">
        <thead>
          <tr>
            <th>Item</th><th>Display name</th><th class="num">Price</th><th class="num">Qty</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$rentItems): ?>
          <tr><td colspan="5" class="muted t-center">No rent items yet — add one using the form on the right.</td></tr>
        <?php else: foreach ($rentItems as $rid => $r):
            $qty = (int) ($r['quantity'] ?? 0);
            $qtyClass = $qty === 0 ? 'qty-out' : ($qty <= 2 ? 'qty-low' : '');
            $img = product_image_url($r['image'] ?? '', $rid, 'rent_items');
        ?>
          <tr>
            <td>
              <div class="img-row">
                <img class="thumb--sm" src="<?= e($img) ?>" alt="">
                <strong><?= e($r['name'] ?? 'Untitled') ?></strong>
              </div>
            </td>
            <td><?= e($r['display_name'] ?? '—') ?></td>
            <td class="num"><?= e(money((float) ($r['price'] ?? 0))) ?></td>
            <td class="num <?= $qtyClass ?>"><?= $qty ?></td>
            <td>
              <div class="row">
                <a class="btn btn--ghost btn--sm" href="/admin/rent_items.php?edit=<?= urlencode((string) $rid) ?>">Edit</a>
                <form method="post" action="/admin/rent_items.php" style="display:inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= e((string) $rid) ?>">
                  <button class="btn btn--danger btn--sm" type="submit"
                          data-confirm="Delete rent item '<?= e($r['name'] ?? '') ?>'? This cannot be undone.">Delete</button>
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
  <div class="card" id="rent-form">
    <div class="card__head">
      <div><h2><?= $editing ? 'Edit rent item' : 'Add rent item' ?></h2></div>
      <?php if ($editing): ?>
        <a class="btn btn--ghost btn--sm" href="/admin/rent_items.php">Cancel edit</a>
      <?php endif; ?>
    </div>
    <div class="card__body">
      <form method="post" action="/admin/rent_items.php" enctype="multipart/form-data" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= e($editId) ?>">

        <div class="field">
          <label for="name">Name</label>
          <input class="input" type="text" id="name" name="name" required value="<?= e($formName) ?>" placeholder="round-table-10">
          <span class="hint">Internal identifier (e.g. round-table-10).</span>
        </div>

        <div class="field">
          <label for="display_name">Display name</label>
          <input class="input" type="text" id="display_name" name="display_name" value="<?= e($formDisplayName) ?>" placeholder="Round Banquet Table (10-seater)">
          <span class="hint">Shown to customers.</span>
        </div>

        <div class="form-grid form-grid--2">
          <div class="field">
            <label for="price">Rental price (₱)</label>
            <input class="input" type="number" id="price" name="price" required min="0" step="0.01" value="<?= e($formPrice) ?>" placeholder="0.00">
          </div>
          <div class="field">
            <label for="quantity">Quantity on hand</label>
            <input class="input" type="number" id="quantity" name="quantity" min="0" step="1" value="<?= e($formQuantity) ?>" placeholder="0">
          </div>
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
          <button class="btn btn--gold" type="submit"><?= $editing ? 'Save changes' : 'Create item' ?></button>
          <?php if ($editing): ?>
            <a class="btn btn--ghost" href="/admin/rent_items.php">Cancel</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
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
