<?php

namespace App\Controllers\User;

/**
 * LoginController — customer sign-in (email + password, or Google Identity Services).
 * On success regenerates the session and stores the customer session keys.
 */
class LoginController {

    public static function render(): void {
        // GOOGLE_CLIENT_ID is defined in config.php (from env var). Empty = not configured.
        $googleConfigured = (defined('GOOGLE_CLIENT_ID') && GOOGLE_CLIENT_ID !== '');

        // Already signed in? Skip the form.
        if (!empty($_SESSION['user_id'])) {
            redirect('/user/products.php');
        }

        $email = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $email = trim((string) post('email', ''));
            $pw    = (string) post('password', '');

            // Rate limit: 5 attempts per 15 minutes per email (fallback to IP).
            $rlKey = 'login_' . ($email !== '' ? $email : ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            if (!rate_limit($rlKey, 5, 900)) {
                flash('Too many login attempts. Try again in 15 minutes.', 'danger');
                redirect('/user/login.php');
            }

            $errors = [];
            if ($email === '' || $pw === '') {
                $errors[] = 'Please enter your email and password.';
            }

            $user   = null;
            $userId = null;
            if (!$errors) {
                $db       = getDB();
                $existing = filter_by(rows($db->retrieve('/user')), 'email', $email);
                if (!$existing) {
                    $errors[] = 'Invalid email or password.';
                } else {
                    $userId = (string) array_key_first($existing);
                    $user   = reset($existing);
                    if (!password_verify($pw, (string) ($user['password_hash'] ?? ''))) {
                        $errors[] = 'Invalid email or password.';
                    }
                }
            }

            if (!$errors && $user && empty($user['email_verified'])) {
                flash('Please verify your email before signing in.', 'warn');
                redirect('/user/verify_otp.php?email=' . urlencode($email));
            }

            if (!$errors && $user) {
                session_regenerate_id(true);
                $_SESSION['user_id']    = $userId;
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name']  = $user['name'] ?? '';
                $_SESSION['user_image'] = $user['profile_image'] ?? '';
                flash('Welcome back, ' . ($_SESSION['user_name'] ?: 'guest') . '.', 'ok');
                redirect('/user/products.php');
            }

            foreach ($errors as $err) {
                flash($err, 'danger');
            }
        }

        $flashes = get_flashes();
        ?>
        <!doctype html>
        <html lang="en">
        <head>
          <meta charset="utf-8">
          <meta name="viewport" content="width=device-width, initial-scale=1">
          <title>Sign in &middot; <?= e(BRAND_NAME) ?></title>
          <meta name="description" content="<?= e(BRAND_NAME) ?> — <?= e(BRAND_TAGLINE) ?>">
          <link rel="preconnect" href="https://fonts.googleapis.com">
          <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
          <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
          <script>(function(){try{var t=localStorage.getItem('ss-theme');var m=window.matchMedia('(prefers-color-scheme: dark)').matches;if(t==='dark'||(!t&&m)){document.documentElement.setAttribute('data-theme','dark')}}catch(e){}})();</script>
          <link rel="stylesheet" href="/assets/css/style.css">
          <style>
            .theme-toggle--floating { position: fixed; top: 16px; right: 16px; z-index: 100; width: 42px; height: 42px; }
            @media (min-width: 901px) { .theme-toggle--floating { top: 20px; right: 24px; } }
            .auth__aside { background-image: linear-gradient(160deg, rgba(42,33,24,.88), rgba(24,18,16,.95)), url('/assets/img/login-bg.png'); background-size: cover; background-position: center; }
          </style>
          <link rel="icon" href="/assets/img/logo.png">
          <?php if ($googleConfigured): ?>
            <script src="https://accounts.google.com/gsi/client" async defer></script>
          <?php endif; ?>
        </head>
        <body>
        <button class="theme-toggle theme-toggle--floating" type="button" aria-label="Toggle dark mode" aria-pressed="false" data-theme-toggle title="Toggle theme">
          <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
          <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </button>
        <div class="auth">
          <aside class="auth__aside">
            <a class="brand" href="/user/login.php">
              <span class="brand__mark"><img src="/assets/img/logo.png" alt="CRATES N' PLATES" class="brand__logo"></span>
              <span>
                <span class="brand__name"><?= e(BRAND_NAME) ?></span><br>
                <span class="brand__tag"><?= e(BRAND_TAGLINE) ?></span>
              </span>
            </a>

            <div>
              <h1 style="font-size:clamp(1.6rem,1.1rem+1.8vw,2.2rem);max-width:18ch;"><?= e(get_settings()['hero_title']) ?></h1>
              <p style="color:#cdbfa6;font-size:15px;max-width:36ch;"><?= e(get_settings()['hero_subtitle']) ?></p>
            </div>

            <div style="display:flex;flex-direction:column;gap:6px;color:#9c8f7b;font-size:13px;line-height:1.7;">
              <div style="display:flex;align-items:center;gap:8px;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#c8a45c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                <?= e(get_settings()['address']) ?>
              </div>
              <div style="display:flex;align-items:center;gap:8px;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#c8a45c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <?= e(get_settings()['hours']) ?>
              </div>
              <a href="/user/about.php" style="color:#c8a45c;font-weight:600;margin-top:6px;display:inline-flex;align-items:center;gap:6px;">About us <span aria-hidden>&rarr;</span></a>
            </div>

            <small style="color:#9c8f7b;">&copy; <?= date('Y') ?> <?= e(BRAND_NAME) ?>. All rights reserved.</small>
          </aside>

          <main class="auth__main">
            <div class="auth__card card card--pad-lg">
              <h2>Sign in</h2>
              <p class="muted mt-0" style="margin-top:2px;">Welcome back to <?= e(BRAND_NAME) ?>.</p>

              <?php foreach ($flashes as $f): ?>
                <div class="alert alert--<?= e($f['type']) ?>" role="status">
                  <span><?= e($f['message']) ?></span>
                </div>
              <?php endforeach; ?>

              <form method="post" action="/user/login.php" autocomplete="on" novalidate>
                <?= csrf_field() ?>
                <div class="form-grid mt-4">
                  <div class="field">
                    <label for="email">Email address</label>
                    <input class="input" id="email" name="email" type="email" autocomplete="email"
                           placeholder="you@example.com" value="<?= e($email) ?>" required>
                  </div>
                  <div class="field">
                    <label for="password">Password</label>
                    <div class="input-wrap">
                      <input class="input" id="password" name="password" type="password" autocomplete="current-password"
                             placeholder="Your password" required>
                    </div>
                    <div style="text-align:right;margin-top:6px;">
                      <a href="/user/forgot_password.php" style="font-size:13px;color:var(--gold);">Forgot password?</a>
                    </div>
                  </div>
                  <div class="form-actions">
                    <button class="btn btn--gold btn--lg btn--block" type="submit">Sign in</button>
                  </div>
                </div>
              </form>

              <?php if ($googleConfigured): ?>
                <div class="divider"></div>
                <div class="t-center muted" style="font-size:13px;margin-bottom:10px;">or continue with</div>
                <div id="g_id_onload"
                     data-client_id="<?= e(GOOGLE_CLIENT_ID) ?>"
                     data-callback="handleGoogle"
                     data-auto_prompt="false"></div>
                <div class="t-center">
                  <div class="g_id_signin" data-type="standard" data-shape="pill" data-size="large" data-theme="outline" data-text="continue_with" data-locale="en"></div>
                </div>
                <form id="googleForm" method="post" action="/user/google_auth.php" style="display:none;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="credential" id="googleCredential">
                </form>
                <script>
                  function handleGoogle(response) {
                    document.getElementById('googleCredential').value = response.credential;
                    document.getElementById('googleForm').submit();
                  }
                </script>
              <?php else: ?>
                <div class="divider"></div>
                <div class="t-center muted" style="font-size:13px;margin-bottom:10px;">or continue with</div>
                <button class="btn btn--outline btn--block" type="button" disabled title="Google sign-in requires a Client ID — see Admin → Settings or config.php">
                  <svg width="18" height="18" viewBox="0 0 48 48"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.4 29.3 35 24 35c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.9 1.2 8 3.1l5.7-5.7C34.2 5.1 29.4 3 24 3 12.4 3 3 12.4 3 24s9.4 21 21 21 21-9.4 21-21c0-1.2-.1-2.3-.4-3.5z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 16 19 13 24 13c3.1 0 5.9 1.2 8 3.1l5.7-5.7C34.2 5.1 29.4 3 24 3 16 3 9.1 7.6 6.3 14.7z"/><path fill="#4CAF50" d="M24 45c5.2 0 10-2 13.6-5.2l-6.3-5.3C29.2 35.9 26.7 37 24 37c-5.3 0-9.7-2.6-11.3-7l-6.5 5C9 40.3 15.9 45 24 45z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.1-4 5.5l6.3 5.3C41.9 35.7 45 30.4 45 24c0-1.2-.1-2.3-.4-3.5z"/></svg>
                  Continue with Google
                </button>
                <p class="muted" style="font-size:11px;text-align:center;margin-top:8px;">Google sign-in is not yet configured.</p>
              <?php endif; ?>

              <p class="auth__switch">
                New here? <a href="/user/signup.php">Create an account</a>
              </p>
            </div>
          </main>
        </div>
        <script src="/assets/js/app.js"></script>
        </body>
        </html>
        <?php
    }
}
