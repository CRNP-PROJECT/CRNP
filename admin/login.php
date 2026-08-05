<?php
/**
 * admin/login.php — Administrator sign-in (standalone .auth shell).
 */
require_once __DIR__ . '/../init.php';
security_headers();

// Already signed in? Skip to dashboard.
if (!empty($_SESSION['admin_email'])) {
    redirect('/admin/');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email    = trim((string) post('email', ''));
    $password = (string) post('password', '');

    // Rate limit: 5 attempts per 15 minutes per email (fallback to IP).
    $rlKey = 'login_' . ($email !== '' ? $email : ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    if (!rate_limit($rlKey, 5, 900)) {
        flash('Too many login attempts. Try again in 15 minutes.', 'danger');
        redirect('/admin/login.php');
    }

    if ($email === '' || $password === '') {
        flash('Please enter your email and password.', 'danger');
    } else {
        $db    = getDB();
        $admins = rows($db->retrieve('/admins'));
        $admin  = null;
        foreach ($admins as $id => $a) {
            if (is_array($a) && !empty($a['email']) && strcasecmp((string) $a['email'], $email) === 0) {
                $admin = $a;
                break;
            }
        }

        if (!$admin || empty($admin['password_hash']) || !password_verify($password, $admin['password_hash'])) {
            flash('Invalid administrator credentials.', 'danger');
        } else {
            session_regenerate_id(true);
            $_SESSION['admin_email'] = $admin['email'];
            $_SESSION['admin_name']  = $admin['name'] ?? 'Administrator';
            flash('Welcome back, ' . ($_SESSION['admin_name']) . '.', 'ok');
            redirect('/admin/');
        }
    }
}

$pageTitle = 'Admin Sign In';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> &middot; <?= e(BRAND_NAME) ?></title>
  <meta name="description" content="<?= e(BRAND_NAME) ?> administrator sign in.">
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
        <span class="eyebrow" style="color:var(--gold);font-size:12px;letter-spacing:.22em;text-transform:uppercase;">Control Center</span>
        <h1>Admin Control.</h1>
        <p>Run the floor from one elegant console. Manage your menu, rentals, staff and revenue. Track every order, every booking, every peso — all in real time.</p>
      </div>

      <small>&copy; <?= date('Y') ?> <?= e(BRAND_NAME) ?> &middot; Authorized personnel only.</small>
    </aside>

    <main class="auth__main">
      <div class="auth__card card card--pad-lg">
        <?php foreach (get_flashes() as $f): ?>
          <div class="alert alert--<?= e($f['type']) ?>" role="status">
            <span><?= e($f['message']) ?></span>
          </div>
        <?php endforeach; ?>

        <h2>Sign in</h2>
        <p class="muted mb-4">Enter your administrator credentials to continue.</p>

        <form method="post" action="/admin/login.php" class="form-grid" novalidate>
          <?= csrf_field() ?>
          <div class="field">
            <label for="email">Email address</label>
            <input class="input" type="email" id="email" name="email" required
                   value="<?= e(post('email', '')) ?>" autocomplete="email" autofocus
                   placeholder="admin@example.com">
          </div>
          <div class="field">
            <label for="password">Password</label>
            <div class="input-wrap">
              <input class="input" type="password" id="password" name="password" required
                     autocomplete="current-password" placeholder="••••••••">
            </div>
          </div>
          <div class="form-actions">
            <button class="btn btn--gold btn--block btn--lg" type="submit">Sign in</button>
          </div>
        </form>

        <p class="auth__switch">Need an administrator account? <a href="/admin/signup.php">Create one</a></p>
      </div>
    </main>
  </div>
<script src="/assets/js/app.js"></script>
</body>
</html>
