<?php

namespace App\Controllers\Kitchen;

/**
 * LoginController — Kitchen staff sign-in (auth shell, standalone HTML).
 * POST finds a /kitchen record by email and verifies the password hash.
 */
class LoginController {

    public static function render(): void {
        // Already signed in? Skip straight to the display.
        if (!empty($_SESSION['kitchen_email'])) {
            redirect('/kitchen/');
        }

        // ----- POST: authenticate -----
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $email    = trim((string)post('email', ''));
            $password = (string)post('password', '');

            // Rate limit: 5 attempts per 15 minutes per email (fallback to IP).
            $rlKey = 'login_' . ($email !== '' ? $email : ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            if (!rate_limit($rlKey, 5, 900)) {
                flash('Too many login attempts. Try again in 15 minutes.', 'danger');
                redirect('/kitchen/login.php');
            }

            if ($email === '' || $password === '') {
                flash('Please fill in both fields.', 'danger');
                redirect('/kitchen/login.php');
            }

            $db    = getDB();
            $staff = filter_by(rows($db->retrieve('/kitchen')), 'email', $email);
            $k     = $staff ? reset($staff) : null;

            if ($k && !empty($k['password_hash']) && password_verify($password, $k['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['kitchen_email'] = $k['email'];
                $_SESSION['kitchen_name']  = $k['full_name'] ?? $k['name'] ?? 'Kitchen';
                flash('Welcome back, ' . $_SESSION['kitchen_name'] . '.', 'ok');
                redirect('/kitchen/');
            }

            flash('Invalid credentials.', 'danger');
            redirect('/kitchen/login.php');
        }

        // ----- GET: render the auth shell -----
        $pageTitle = 'Kitchen Sign In';
        ?>
        <!doctype html>
        <html lang="en">
        <head>
          <meta charset="utf-8">
          <meta name="viewport" content="width=device-width, initial-scale=1">
          <title><?= e($pageTitle) ?> &middot; <?= e(BRAND_NAME) ?></title>
          <meta name="description" content="<?= e(BRAND_NAME) ?> kitchen display sign in.">
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
            <a class="brand" href="/kitchen/login.php">
              <span class="brand__mark"><img src="/assets/img/logo.png" alt="CRATES N' PLATES" class="brand__logo"></span>
              <span>
                <span class="brand__name"><?= e(BRAND_NAME) ?></span><br>
                <span class="brand__tag">Kitchen Display</span>
              </span>
            </a>
            <div>
              <h1>Run a calm, on-time line.</h1>
              <p>Track incoming tickets, move orders from accepted to ready, and keep the front-of-house perfectly in sync &mdash; all from one focused kitchen view.</p>
            </div>
            <small>&copy; <?= date('Y') ?> <?= e(BRAND_NAME) ?></small>
          </aside>

          <main class="auth__main">
            <div class="auth__card card card--pad-lg">
              <h2>Kitchen sign in</h2>
              <p class="muted mb-4">Use your staff email and password to open the display.</p>

              <?php foreach (get_flashes() as $f): ?>
                <div class="alert alert--<?= e($f['type']) ?>" role="status">
                  <span><?= e($f['message']) ?></span>
                </div>
              <?php endforeach; ?>

              <form method="post" action="/kitchen/login.php" class="form-grid" autocomplete="on" novalidate>
                <?= csrf_field() ?>
                <div class="field">
                  <label for="email">Staff email</label>
                  <input class="input" type="email" id="email" name="email"
                         placeholder="you@kitchen.local" required
                         autocomplete="username" autofocus>
                </div>
                <div class="field">
                  <label for="password">Password</label>
                  <div class="input-wrap">
                    <input class="input" type="password" id="password" name="password"
                           placeholder="Your password" required
                           autocomplete="current-password">
                  </div>
                </div>
                <div class="form-actions">
                  <button type="submit" class="btn btn--gold btn--block btn--lg">Open display</button>
                </div>
              </form>

              <div class="auth__switch">Need access? Ask your manager for a kitchen account.</div>
            </div>
          </main>
        </div>
        <script src="/assets/js/app.js"></script>
        </body>
        </html>
        <?php
    }
}
