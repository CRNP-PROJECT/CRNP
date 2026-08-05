<?php
/**
 * cashier/login.php — Cashier Console sign-in.
 * Standalone full HTML doc using the .auth shell (no header/footer).
 */
require_once __DIR__ . '/../init.php';
security_headers();

// Already signed in? Go to the console.
if (!empty($_SESSION['cashier_email'])) {
    redirect('/cashier/');
}

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email    = trim((string)post('email', ''));
    $password = (string)post('password', '');

    // Rate limit: 5 attempts per 15 minutes per email (fallback to IP).
    $rlKey = 'login_' . ($email !== '' ? $email : ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    if (!rate_limit($rlKey, 5, 900)) {
        flash('Too many login attempts. Try again in 15 minutes.', 'danger');
        redirect('/cashier/login.php');
    }

    if ($email === '' || $password === '') {
        flash('Please fill in both your email and password.', 'danger');
        redirect('/cashier/login.php');
    }

    $cashier = null;
    foreach (rows($db->retrieve('/cashiers')) as $row) {
        if (is_array($row) && !empty($row['email'])
            && strcasecmp((string)$row['email'], $email) === 0) {
            $cashier = $row;
            break;
        }
    }

    if (!$cashier || empty($cashier['password_hash'])
        || !password_verify($password, (string)$cashier['password_hash'])) {
        flash('Invalid credentials. Please try again.', 'danger');
        redirect('/cashier/login.php');
    }

    // Success
    session_regenerate_id(true);
    $_SESSION['cashier_email'] = $cashier['email'];
    $_SESSION['cashier_name']  = $cashier['name'] ?? $cashier['full_name'] ?? 'Cashier';

    flash('Welcome back, ' . $_SESSION['cashier_name'] . '.', 'ok');
    redirect('/cashier/');
}

$pageTitle = 'Cashier Sign In';
$flashes   = get_flashes();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> &middot; <?= e(BRAND_NAME) ?></title>
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
    <a class="brand" href="/cashier/login.php">
      <span class="brand__mark"><img src="/assets/img/logo.png" alt="CRATES N' PLATES" class="brand__logo"></span>
      <span>
        <span class="brand__name"><?= e(BRAND_NAME) ?></span><br>
        <span class="brand__tag">Cashier Console</span>
      </span>
    </a>

    <div>
      <span class="eyebrow" style="color:var(--gold)">Front of House</span>
      <h1>The Cashier Console</h1>
      <p>Take command of customer orders, verify GCash payments, and orchestrate rental bookings &mdash; all from one calm, focused dashboard.</p>
    </div>

    <small>&copy; <?= date('Y') ?> <?= e(BRAND_NAME) ?> &middot; <?= e(BRAND_TAGLINE) ?></small>
  </aside>

  <main class="auth__main">
    <div class="auth__card card card--pad-lg">
      <h2>Sign in</h2>
      <p class="muted mb-4">Use your cashier credentials to access the console.</p>

      <?php foreach ($flashes as $f): ?>
        <div class="alert alert--<?= e($f['type']) ?>" role="status">
          <span><?= e($f['message']) ?></span>
        </div>
      <?php endforeach; ?>

      <form method="post" autocomplete="on" class="form-grid">
        <?= csrf_field() ?>
        <div class="field">
          <label for="email">Email address</label>
          <input class="input" type="email" id="email" name="email"
                 autocomplete="username" required
                 placeholder="you@cratesnplates.diner">
        </div>
        <div class="field">
          <label for="password">Password</label>
          <div class="input-wrap">
            <input class="input" type="password" id="password" name="password"
                   autocomplete="current-password" required
                   placeholder="Your password">
          </div>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn btn--gold btn--block btn--lg">Sign in to console</button>
        </div>
      </form>

      <p class="auth__switch">Need an account? Contact your <a href="#">administrator</a>.</p>
    </div>
  </main>
</div>
<script src="/assets/js/app.js"></script>
</body>
</html>
