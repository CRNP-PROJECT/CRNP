<?php
/**
 * admin/staff.php — Manage cashier & kitchen accounts.
 */
require_once __DIR__ . '/../init.php';
require_admin();
use App\Models\Staff;

/* ---------- POST handling ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action   = (string) post('action', '');
    $role     = (string) post('role', '');
    $email    = trim((string) post('email', ''));
    $password = (string) post('password', '');

    if ($action === 'delete') {
        $id   = (string) post('id', '');
        $path = $role === 'kitchen' ? '/kitchen' : '/cashiers';
        try {
            $db->delete($path, $id);
            flash(($role === 'kitchen' ? 'Kitchen staff' : 'Cashier') . ' deleted.', 'ok');
        } catch (Throwable $ex) {
            flash('Could not delete: ' . $ex->getMessage(), 'danger');
        }
        redirect('/admin/staff.php');
    }

    if ($action === 'save') {
        $id   = (string) post('id', '');
        $role = (string) post('role', '');
        $email = trim((string) post('email', ''));
        if ($id === '' || !in_array($role, ['cashier', 'kitchen'], true) || $email === '') {
            flash('Invalid request.', 'danger');
            redirect('/admin/staff.php');
        }
        $password = (string) post('password', '');
        try {
            if ($role === 'cashier') {
                $name = trim((string) post('name', ''));
                $data = ['name' => $name, 'email' => $email];
                if ($password !== '') $data['password_hash'] = password_hash($password, PASSWORD_BCRYPT);
                $db->update('/cashiers', $id, $data);
            } else {
                $fullName = trim((string) post('full_name', ''));
                $data = ['full_name' => $fullName, 'email' => $email];
                if ($password !== '') $data['password_hash'] = password_hash($password, PASSWORD_BCRYPT);
                $db->update('/kitchen', $id, $data);
            }
            flash(($role === 'kitchen' ? 'Kitchen staff' : 'Cashier') . ' updated.', 'ok');
        } catch (Throwable $ex) {
            flash('Could not save: ' . $ex->getMessage(), 'danger');
        }
        redirect('/admin/staff.php');
    }

    if ($role === 'cashier') {
        $name = trim((string) post('name', ''));
        if ($name === '' || $email === '' || $password === '') {
            flash('All cashier fields are required.', 'danger');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('Please enter a valid email.', 'danger');
        } elseif (strlen($password) < 8) {
            flash('Cashier password must be at least 8 characters.', 'danger');
        } elseif (Staff::emailExists('cashiers', $email)) {
            flash('A cashier with that email already exists.', 'danger');
        } else {
            try {
                Staff::createIn('cashiers', [
                    'name'          => $name,
                    'email'         => $email,
                    'password_hash' => password_hash($password, PASSWORD_BCRYPT),
                    'created_at'    => now(),
                ]);
                flash('Cashier account created.', 'ok');
            } catch (Throwable $ex) {
                flash('Could not create cashier: ' . $ex->getMessage(), 'danger');
            }
        }
        redirect('/admin/staff.php#cashiers');
    }

    if ($role === 'kitchen') {
        $fullName = trim((string) post('full_name', ''));
        if ($fullName === '' || $email === '' || $password === '') {
            flash('All kitchen fields are required.', 'danger');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('Please enter a valid email.', 'danger');
        } elseif (strlen($password) < 8) {
            flash('Kitchen password must be at least 8 characters.', 'danger');
        } elseif (Staff::emailExists('kitchen', $email)) {
            flash('A kitchen staff member with that email already exists.', 'danger');
        } else {
            try {
                Staff::createIn('kitchen', [
                    'full_name'     => $fullName,
                    'email'         => $email,
                    'password_hash' => password_hash($password, PASSWORD_BCRYPT),
                    'created_at'    => now(),
                ]);
                flash('Kitchen staff account created.', 'ok');
            } catch (Throwable $ex) {
                flash('Could not create kitchen staff: ' . $ex->getMessage(), 'danger');
            }
        }
        redirect('/admin/staff.php#kitchen');
    }

    flash('Unknown action.', 'danger');
    redirect('/admin/staff.php');
}

/* ---------- Edit mode ---------- */
$editId   = isset($_GET['edit']) ? (string) $_GET['edit'] : '';
$editRole = isset($_GET['role']) ? (string) $_GET['role'] : '';
$editing  = null;
if ($editId !== '' && in_array($editRole, ['cashier', 'kitchen'], true)) {
    $path = $editRole === 'kitchen' ? '/kitchen' : '/cashiers';
    $editing = $db->retrieve($path . '/' . $editId);
    if (!is_array($editing)) {
        flash('Staff member not found.', 'warn');
        redirect('/admin/staff.php');
    }
}
$isEditingCashier = ($editRole === 'cashier' && $editing !== null);
$isEditingKitchen = ($editRole === 'kitchen' && $editing !== null);

/* ---------- List data ---------- */
$cashiers = Staff::allFrom('cashiers');
uasort($cashiers, function ($a, $b) {
    $ta = strtotime((string) ($a['created_at'] ?? '')) ?: 0;
    $tb = strtotime((string) ($b['created_at'] ?? '')) ?: 0;
    return $tb <=> $ta;
});

$kitchen = Staff::allFrom('kitchen');
uasort($kitchen, function ($a, $b) {
    $ta = strtotime((string) ($a['created_at'] ?? '')) ?: 0;
    $tb = strtotime((string) ($b['created_at'] ?? '')) ?: 0;
    return $tb <=> $ta;
});

$pageTitle = 'Staff';
$activeNav = 'staff';
$layout    = 'wide';
require_once __DIR__ . '/../includes/header.php';
?>
<style>
  .layout-2 { display:grid; grid-template-columns:1fr 1fr; gap:24px; align-items:start; }
  @media (max-width:980px) { .layout-2 { grid-template-columns:1fr; } }
  .micro { font-size:12px; color:var(--muted); }
  .avatar {
    width:38px; height:38px; border-radius:999px;
    background:linear-gradient(150deg,#2a2118,#211b14); color:var(--gold-100);
    display:grid; place-items: center; font-family:var(--sans); font-weight:700; font-size:15px;
  }
  .form-card { position:sticky; top:90px; }
</style>

<div class="page-head">
  <div class="page-head__row">
    <div>
      <span class="eyebrow">Team</span>
      <h1 class="mt-2">Staff</h1>
      <p>Create cashier and kitchen accounts. Passwords are stored hashed — they are never displayed.</p>
    </div>
  </div>
</div>

<div class="layout-2">
  <!-- Cashiers -->
  <section id="cashiers">
    <div class="card">
      <div class="card__head">
        <div><h2>Cashiers</h2><small><?= count($cashiers) ?> account(s)</small></div>
      </div>
      <div class="card__body">
        <form method="post" action="/admin/staff.php#cashiers" class="form-grid">
          <?= csrf_field() ?>
          <input type="hidden" name="role" value="cashier">
          <?php if ($isEditingCashier): ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= e((string) $editId) ?>">
          <?php endif; ?>
          <div class="field">
            <label for="c_name">Name</label>
            <input class="input" type="text" id="c_name" name="name" required
                   value="<?= $isEditingCashier ? e($editing['name'] ?? '') : e(post('role') === 'cashier' ? post('name') : '') ?>"
                   placeholder="Ana Reyes">
          </div>
          <div class="form-grid form-grid--2">
            <div class="field">
              <label for="c_email">Email</label>
              <input class="input" type="email" id="c_email" name="email" required
                     value="<?= $isEditingCashier ? e($editing['email'] ?? '') : '' ?>"
                     placeholder="ana@cratesnplates.diner">
            </div>
            <div class="field">
              <label for="c_password">Password</label>
              <div class="input-wrap">
                <input class="input" type="password" id="c_password" name="password"
                       <?= $isEditingCashier ? '' : 'required minlength="8"' ?>
                       placeholder="<?= $isEditingCashier ? 'Leave blank to keep current' : '••••••••' ?>">
              </div>
            </div>
          </div>
          <div class="form-actions">
            <button class="btn btn--gold" type="submit"><?= $isEditingCashier ? 'Save changes' : 'Create cashier' ?></button>
            <?php if ($isEditingCashier): ?>
              <a class="btn btn--ghost" href="/admin/staff.php">Cancel</a>
            <?php endif; ?>
          </div>
          <?php if ($isEditingCashier): ?>
            <span class="hint">Leave password blank to keep the current one.</span>
          <?php else: ?>
            <span class="hint">Password must be at least 8 characters.</span>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <div class="card mt-4">
      <div class="card__head">
        <div><h3>Existing cashiers</h3></div>
      </div>
      <div class="table-wrap" style="border:0;border-radius:0;">
        <table class="tbl">
          <thead>
            <tr><th>Staff</th><th>Email</th><th>Created</th><th>Actions</th></tr>
          </thead>
          <tbody>
          <?php if (!$cashiers): ?>
            <tr><td colspan="4" class="muted t-center">No cashiers yet.</td></tr>
          <?php else: foreach ($cashiers as $cid => $c):
              $initial = strtoupper(mb_substr((string) ($c['name'] ?? '?'), 0, 1));
              $created = (string) ($c['created_at'] ?? '');
          ?>
            <tr>
              <td>
                <div class="img-row" style="display:flex;align-items:center;gap:10px;">
                  <span class="avatar"><?= e($initial) ?></span>
                  <strong><?= e($c['name'] ?? 'Cashier') ?></strong>
                </div>
              </td>
              <td class="micro"><?= e($c['email'] ?? '—') ?></td>
              <td class="micro"><?= $created ? e(date('M j, Y', strtotime($created))) : '—' ?></td>
              <td>
                <div class="row" style="flex-wrap:nowrap">
                  <a class="btn btn--ghost btn--sm" href="/admin/staff.php?edit=<?= urlencode((string) $cid) ?>&role=cashier">Edit</a>
                  <form method="post" action="/admin/staff.php" style="display:inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="role" value="cashier">
                    <input type="hidden" name="id" value="<?= e((string) $cid) ?>">
                    <button class="btn btn--danger btn--sm" type="submit"
                            data-confirm="Delete cashier '<?= e($c['name'] ?? '') ?>'?">Delete</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <!-- Kitchen -->
  <section id="kitchen">
    <div class="card">
      <div class="card__head">
        <div><h2>Kitchen staff</h2><small><?= count($kitchen) ?> account(s)</small></div>
      </div>
      <div class="card__body">
        <form method="post" action="/admin/staff.php#kitchen" class="form-grid">
          <?= csrf_field() ?>
          <input type="hidden" name="role" value="kitchen">
          <?php if ($isEditingKitchen): ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= e((string) $editId) ?>">
          <?php endif; ?>
          <div class="field">
            <label for="k_full_name">Full name</label>
            <input class="input" type="text" id="k_full_name" name="full_name" required
                   value="<?= $isEditingKitchen ? e($editing['full_name'] ?? '') : e(post('role') === 'kitchen' ? post('full_name') : '') ?>"
                   placeholder="Jose Cruz">
          </div>
          <div class="form-grid form-grid--2">
            <div class="field">
              <label for="k_email">Email</label>
              <input class="input" type="email" id="k_email" name="email" required
                     value="<?= $isEditingKitchen ? e($editing['email'] ?? '') : '' ?>"
                     placeholder="jose@cratesnplates.diner">
            </div>
            <div class="field">
              <label for="k_password">Password</label>
              <div class="input-wrap">
                <input class="input" type="password" id="k_password" name="password"
                       <?= $isEditingKitchen ? '' : 'required minlength="8"' ?>
                       placeholder="<?= $isEditingKitchen ? 'Leave blank to keep current' : '••••••••' ?>">
              </div>
            </div>
          </div>
          <div class="form-actions">
            <button class="btn btn--gold" type="submit"><?= $isEditingKitchen ? 'Save changes' : 'Create kitchen staff' ?></button>
            <?php if ($isEditingKitchen): ?>
              <a class="btn btn--ghost" href="/admin/staff.php">Cancel</a>
            <?php endif; ?>
          </div>
          <?php if ($isEditingKitchen): ?>
            <span class="hint">Leave password blank to keep the current one.</span>
          <?php else: ?>
            <span class="hint">Password must be at least 8 characters.</span>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <div class="card mt-4">
      <div class="card__head">
        <div><h3>Existing kitchen staff</h3></div>
      </div>
      <div class="table-wrap" style="border:0;border-radius:0;">
        <table class="tbl">
          <thead>
            <tr><th>Staff</th><th>Email</th><th>Created</th><th>Actions</th></tr>
          </thead>
          <tbody>
          <?php if (!$kitchen): ?>
            <tr><td colspan="4" class="muted t-center">No kitchen staff yet.</td></tr>
          <?php else: foreach ($kitchen as $kid => $k):
              $initial = strtoupper(mb_substr((string) ($k['full_name'] ?? '?'), 0, 1));
              $created = (string) ($k['created_at'] ?? '');
          ?>
            <tr>
              <td>
                <div class="img-row" style="display:flex;align-items:center;gap:10px;">
                  <span class="avatar"><?= e($initial) ?></span>
                  <strong><?= e($k['full_name'] ?? 'Kitchen') ?></strong>
                </div>
              </td>
              <td class="micro"><?= e($k['email'] ?? '—') ?></td>
              <td class="micro"><?= $created ? e(date('M j, Y', strtotime($created))) : '—' ?></td>
              <td>
                <div class="row" style="flex-wrap:nowrap">
                  <a class="btn btn--ghost btn--sm" href="/admin/staff.php?edit=<?= urlencode((string) $kid) ?>&role=kitchen">Edit</a>
                  <form method="post" action="/admin/staff.php" style="display:inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="role" value="kitchen">
                    <input type="hidden" name="id" value="<?= e((string) $kid) ?>">
                    <button class="btn btn--danger btn--sm" type="submit"
                            data-confirm="Delete kitchen staff '<?= e($k['full_name'] ?? '') ?>'?">Delete</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
