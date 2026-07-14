<?php
/**
 * admin/staff.php — Manage cashier & kitchen accounts.
 */
require_once __DIR__ . '/../init.php';
require_admin();

$db = getDB();

/* ---------- POST handling ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $role     = (string) post('role', '');
    $email    = trim((string) post('email', ''));
    $password = (string) post('password', '');

    if ($role === 'cashier') {
        $name = trim((string) post('name', ''));
        if ($name === '' || $email === '' || $password === '') {
            flash('All cashier fields are required.', 'danger');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('Please enter a valid email.', 'danger');
        } elseif (strlen($password) < 8) {
            flash('Cashier password must be at least 8 characters.', 'danger');
        } else {
            $existing = rows($db->retrieve('/cashiers'));
            $dup = false;
            foreach ($existing as $c) {
                if (is_array($c) && !empty($c['email']) && strcasecmp((string) $c['email'], $email) === 0) {
                    $dup = true; break;
                }
            }
            if ($dup) {
                flash('A cashier with that email already exists.', 'danger');
            } else {
                try {
                    $db->insert('/cashiers', [
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
        } else {
            $existing = rows($db->retrieve('/kitchen'));
            $dup = false;
            foreach ($existing as $k) {
                if (is_array($k) && !empty($k['email']) && strcasecmp((string) $k['email'], $email) === 0) {
                    $dup = true; break;
                }
            }
            if ($dup) {
                flash('A kitchen staff member with that email already exists.', 'danger');
            } else {
                try {
                    $db->insert('/kitchen', [
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
        }
        redirect('/admin/staff.php#kitchen');
    }

    flash('Unknown action.', 'danger');
    redirect('/admin/staff.php');
}

/* ---------- List data ---------- */
$cashiers = rows($db->retrieve('/cashiers'));
uasort($cashiers, function ($a, $b) {
    $ta = strtotime((string) ($a['created_at'] ?? '')) ?: 0;
    $tb = strtotime((string) ($b['created_at'] ?? '')) ?: 0;
    return $tb <=> $ta;
});

$kitchen = rows($db->retrieve('/kitchen'));
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
    display:grid; place-items: center; font-family:var(--serif); font-weight:700; font-size:15px;
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
          <div class="field">
            <label for="c_name">Name</label>
            <input class="input" type="text" id="c_name" name="name" required value="<?= e(post('role') === 'cashier' ? post('name') : '') ?>" placeholder="Ana Reyes">
          </div>
          <div class="form-grid form-grid--2">
            <div class="field">
              <label for="c_email">Email</label>
              <input class="input" type="email" id="c_email" name="email" required placeholder="ana@cratesnplates.diner">
            </div>
            <div class="field">
              <label for="c_password">Password</label>
              <input class="input" type="password" id="c_password" name="password" required minlength="8" placeholder="••••••••">
            </div>
          </div>
          <div class="form-actions">
            <button class="btn btn--gold" type="submit">Create cashier</button>
          </div>
          <span class="hint">Password must be at least 8 characters.</span>
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
            <tr><th>Staff</th><th>Email</th><th>Created</th></tr>
          </thead>
          <tbody>
          <?php if (!$cashiers): ?>
            <tr><td colspan="3" class="muted t-center">No cashiers yet.</td></tr>
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
          <div class="field">
            <label for="k_full_name">Full name</label>
            <input class="input" type="text" id="k_full_name" name="full_name" required value="<?= e(post('role') === 'kitchen' ? post('full_name') : '') ?>" placeholder="Jose Cruz">
          </div>
          <div class="form-grid form-grid--2">
            <div class="field">
              <label for="k_email">Email</label>
              <input class="input" type="email" id="k_email" name="email" required placeholder="jose@cratesnplates.diner">
            </div>
            <div class="field">
              <label for="k_password">Password</label>
              <input class="input" type="password" id="k_password" name="password" required minlength="8" placeholder="••••••••">
            </div>
          </div>
          <div class="form-actions">
            <button class="btn btn--gold" type="submit">Create kitchen staff</button>
          </div>
          <span class="hint">Password must be at least 8 characters.</span>
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
            <tr><th>Staff</th><th>Email</th><th>Created</th></tr>
          </thead>
          <tbody>
          <?php if (!$kitchen): ?>
            <tr><td colspan="3" class="muted t-center">No kitchen staff yet.</td></tr>
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
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
