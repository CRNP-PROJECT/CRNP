<?php
/**
 * admin/signup.php — Bootstrap the first administrator account.
 *
 * ⚠️  WARNING: Remove this file after creating your first admin.
 *
 * P0 hardening: this page is only reachable when NO admin accounts exist yet.
 * As soon as the first admin is created, subsequent requests are bounced to the
 * login screen. This eliminates the signup form as an attack surface in
 * production while preserving the zero-config first-run bootstrap flow.
 */
require_once __DIR__ . '/../init.php';
security_headers();

if (!empty($_SESSION['admin_email'])) {
    redirect('/admin/');
}

/* P0: auto-disable the signup form once at least one admin exists.
   This runs before any output/POST handling so neither the form nor the
   POST endpoint is reachable after the first admin is created. */
$db = getDB();
if (count(rows($db->retrieve('/admins'))) >= 1) {
    flash('Admin registration is closed. Please sign in.', 'danger');
    redirect('/admin/login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name     = trim((string) post('name', ''));
    $email    = trim((string) post('email', ''));
    $password = (string) post('password', '');

    if ($name === '' || $email === '' || $password === '') {
        flash('All fields are required.', 'danger');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('Please enter a valid email address.', 'danger');
    } elseif (strlen($password) < 8) {
        flash('Password must be at least 8 characters.', 'danger');
    } else {
        $db     = getDB();
        $admins = rows($db->retrieve('/admins'));
        $exists = false;
        foreach ($admins as $a) {
            if (is_array($a) && !empty($a['email']) && strcasecmp((string) $a['email'], $email) === 0) {
                $exists = true;
                break;
            }
        }

        if ($exists) {
            flash('An admin with that email already exists.', 'danger');
        } else {
            try {
                $db->insert('/admins', [
                    'name'          => $name,
                    'email'         => $email,
                    'password_hash' => password_hash($password, PASSWORD_BCRYPT),
                    'created_at'    => now(),
                ]);
                flash('Admin account created, please sign in.', 'ok');
                redirect('/admin/login.php');
            } catch (Throwable $ex) {
                flash('Could not create admin account: ' . $ex->getMessage(), 'danger');
            }
        }
    }
}

$pageTitle = 'Create Admin';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> &middot; <?= e(BRAND_NAME) ?></title>
  <meta name="description" content="Create a <?= e(BRAND_NAME) ?> administrator account.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
  <script>(function(){try{var t=localStorage.getItem('ss-theme');var m=window.matchMedia('(prefers-color-scheme: dark)').matches;if(t==='dark'||(!t&&m)){document.documentElement.setAttribute('data-theme','dark')}}catch(e){}})();</script>
  <link rel="stylesheet" href="/assets/css/style.css">
  <style>
    .theme-toggle--floating { position: fixed; top: 16px; right: 16px; z-index: 100; width: 42px; height: 42px; }
    @media (min-width: 901px) { .theme-toggle--floating { top: 20px; right: 24px; } }
  </style>
  <link rel="icon" href="/assets/img/logo.png">
</head>
<body>
<button class="theme-toggle theme-toggle--floating" type="button" aria-label="Toggle dark mode" aria-pressed="false" data-theme-toggle title="Toggle theme">
  <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
  <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
</button>
  <div class="auth">
    <aside class="auth__aside">
      <a class="brand" href="/admin/login.php">
        <span class="brand__mark"><img src="/assets/img/logo.png" alt="CRATES N' PLATES" class="brand__logo"></span>
        <span>
          <span class="brand__name"><?= e(BRAND_NAME) ?></span><br>
          <span class="brand__tag"><?= e(BRAND_TAGLINE) ?></span>
        </span>
      </a>

      <div>
        <span class="eyebrow" style="color:var(--gold);font-size:12px;letter-spacing:.22em;text-transform:uppercase;">First Run</span>
        <h1>Create your admin.</h1>
        <p>Bootstrap the control center with your first administrator account. Once set up, sign in to manage the full restaurant suite.</p>
      </div>

      <small>&copy; <?= date('Y') ?> <?= e(BRAND_NAME) ?></small>
    </aside>

    <main class="auth__main">
      <div class="auth__card card card--pad-lg">
        <?php foreach (get_flashes() as $f): ?>
          <div class="alert alert--<?= e($f['type']) ?>" role="status">
            <span><?= e($f['message']) ?></span>
          </div>
        <?php endforeach; ?>

        <div class="alert alert--warn" role="status">
          <span><strong>Security notice:</strong> Remove this file after creating your first admin.</span>
        </div>

        <h2>Create admin</h2>
        <p class="muted mb-4">Set up the first administrator account.</p>

        <form method="post" action="/admin/signup.php" class="form-grid" novalidate>
          <?= csrf_field() ?>
          <div class="field">
            <label for="name">Full name</label>
            <input class="input" type="text" id="name" name="name" required
                   value="<?= e(post('name', '')) ?>" autocomplete="name"
                   placeholder="Maria Santos">
          </div>
          <div class="field">
            <label for="email">Email address</label>
            <input class="input" type="email" id="email" name="email" required
                   value="<?= e(post('email', '')) ?>" autocomplete="email"
                   placeholder="admin@example.com">
          </div>
          <div class="field">
            <label for="password">Password</label>
            <input class="input" type="password" id="password" name="password" required
                   autocomplete="new-password" minlength="8"
                   placeholder="At least 8 characters">
            <span class="hint">Use at least 8 characters.</span>
          </div>
          <div class="form-actions">
            <button class="btn btn--gold btn--block btn--lg" type="submit">Create admin account</button>
          </div>
        </form>

        <p class="auth__switch">Already have an account? <a href="/admin/login.php">Sign in</a></p>
      </div>
    </main>
  </div>
<script src="/assets/js/app.js"></script>
</body>
</html>
