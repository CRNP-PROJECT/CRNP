<?php

namespace App\Controllers\User;

/**
 * SignupController — customer registration.
 * Collects name/email/password, generates an OTP, inserts a pending /user
 * record (or refreshes an unverified one), emails the OTP, and redirects
 * to verify_otp.php?email=...
 */
class SignupController {

    public static function render(): void {
        if (!defined('GOOGLE_CLIENT_ID')) {
            define('GOOGLE_CLIENT_ID', 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com');
        }
        $googleConfigured = (GOOGLE_CLIENT_ID !== 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com');

        $name  = '';
        $email = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $name  = trim((string) post('name', ''));
            $email = trim((string) post('email', ''));
            $pw    = (string) post('password', '');

            $errors = [];
            if ($name === '') {
                $errors[] = 'Please enter your full name.';
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Please enter a valid email address.';
            }
            if (strlen($pw) < 8) {
                $errors[] = 'Password must be at least 8 characters long.';
            }

            $db        = getDB();
            $existing  = [];
            $existingId = null;
            if (!$errors) {
                $existing = filter_by(rows($db->retrieve('/user')), 'email', $email);
                if ($existing) {
                    $existingId = (string) array_key_first($existing);
                    $first = reset($existing);
                    if (!empty($first['email_verified'])) {
                        $errors[] = 'That email is already registered. Try signing in.';
                    }
                }
            }

            if (!$errors) {
                // Rate limit: 3 OTP generations per 15 minutes per email.
                if (!rate_limit('signup_otp_' . $email, 3, 900)) {
                    flash('Too many signup attempts for that email. Please try again in 15 minutes.', 'danger');
                    redirect('/user/signup.php');
                }

                $otp    = gen_otp();
                $hash   = password_hash($pw, PASSWORD_BCRYPT);
                $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

                try {
                    if ($existingId) {
                        // Refresh an existing unverified record so we don't duplicate.
                        $db->update('/user', $existingId, [
                            'name'          => $name,
                            'password_hash' => $hash,
                            'otp'           => $otp,
                            'otp_expires'   => $expires,
                            'provider'      => 'email',
                        ]);
                    } else {
                        $db->insert('/user', [
                            'name'           => $name,
                            'email'          => $email,
                            'password_hash'  => $hash,
                            'email_verified' => false,
                            'otp'            => $otp,
                            'otp_expires'    => $expires,
                            'created_at'     => now(),
                            'profile_image'  => '',
                            'provider'       => 'email',
                        ]);
                    }
                } catch (Throwable $e) {
                    $errors[] = 'We couldn\'t create your account right now. Please try again.';
                }
            }

            if (!$errors) {
                $sent = sendOTP($email, $otp);
                if (!$sent) {
                    // P0: never leak the OTP in production. Only surface the dev
                    // fallback when the host has explicitly opted into DEV_MODE.
                    if (defined('DEV_MODE') && DEV_MODE) {
                        flash('SMTP not configured — OTP is ' . $otp . ' (dev only).', 'warn');
                    } else {
                        flash('Could not send verification email. Please try again or contact support.', 'danger');
                    }
                } else {
                    flash('We sent a 6-digit verification code to ' . $email . '.', 'info');
                }
                redirect('/user/verify_otp.php?email=' . urlencode($email));
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
          <title>Create account &middot; <?= e(BRAND_NAME) ?></title>
          <meta name="description" content="<?= e(BRAND_NAME) ?> — <?= e(BRAND_TAGLINE) ?>">
          <link rel="preconnect" href="https://fonts.googleapis.com">
          <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
          <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
          <script>(function(){try{var t=localStorage.getItem('ss-theme');var m=window.matchMedia('(prefers-color-scheme: dark)').matches;if(t==='dark'||(!t&&m)){document.documentElement.setAttribute('data-theme','dark')}}catch(e){}})();</script>
          <link rel="stylesheet" href="/assets/css/style.css">
          <style>
            .theme-toggle--floating { position: fixed; top: 16px; right: 16px; z-index: 100; width: 42px; height: 42px; }
            @media (min-width: 901px) { .theme-toggle--floating { top: 20px; right: 24px; } }

            .auth {
              height: 100vh;
              height: 100dvh;
              min-height: 0;
              overflow: hidden;
            }

            .auth__aside {
              height: 100%;
              overflow: hidden;
            }

            .auth__main {
              height: 100vh;
              height: 100dvh;
              padding: 20px;
              overflow-y: auto;
              overflow-x: hidden;
              scrollbar-width: none;
              -ms-overflow-style: none;
              display: flex;
              flex-direction: column;
              align-items: center;
            }

            .auth__main::-webkit-scrollbar {
              display: none;
            }

            .auth__card {
              width: 100%;
              max-height: none;
              overflow: hidden;
              flex-shrink: 0;
              margin: auto 0;
            }

            .auth__card.card--pad-lg {
              padding: 14px 20px;
            }

            .auth__card::before {
              border-radius: var(--radius-lg) var(--radius-lg) 0 0;
            }

            .auth__card h2 {
              position: static;
              background: transparent;
              margin: 0 0 2px;
              padding: 0;
              font-size: 1.35rem;
            }

            .auth__card .mt-4 { margin-top: 4px; }
            .auth__card .form-grid { gap: 10px; }
            .auth__card .field { gap: 3px; }
            .auth__card .input { min-height: 40px; padding: 9px 13px; }
            .auth__card .btn--lg { min-height: 42px; padding: 10px 24px; }
            .auth__card .form-actions { margin-top: 2px; }
            .auth__card .divider { margin: 6px 0; }
            .auth__card .t-center.muted { margin-bottom: 4px; font-size: 12px; }
            .auth__card .g_id_signin { margin-bottom: 0; }

            .auth__switch {
              margin-top: 4px;
              padding-top: 6px;
            }

            .pwd-toggle { right: 4px; }

            @media (max-width: 479px) {
              .auth__main { padding: 16px 14px; }
              .auth__card.card--pad-lg { padding: 14px 16px; }
            }
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
              <span class="eyebrow" style="font-size:12px;letter-spacing:.22em;text-transform:uppercase;color:var(--gold);">A seat at our table</span>
              <h1>Crates of flavor, plated with care.</h1>
              <p>Join <?= e(BRAND_NAME) ?> to order ahead, reserve tables, and rent our dinnerware for your own gatherings.</p>
              <ul style="list-style:none;padding:0;margin:18px 0 0;display:flex;flex-direction:column;gap:12px;color:#cdbfa6;font-size:14px;">
                <li style="display:flex;gap:10px;align-items:flex-start;"><span style="color:var(--gold);">&#10038;</span> Curated seasonal menus, made to order</li>
                <li style="display:flex;gap:10px;align-items:flex-start;"><span style="color:var(--gold);">&#10038;</span> Priority pickup &amp; delivery tracking</li>
                <li style="display:flex;gap:10px;align-items:flex-start;"><span style="color:var(--gold);">&#10038;</span> Tableware rentals for private gatherings</li>
                <li style="display:flex;gap:10px;align-items:flex-start;"><span style="color:var(--gold);">&#10038;</span> Loyalty rewards on every visit</li>
              </ul>
            </div>

            <small style="color:#9c8f7b;">&copy; <?= date('Y') ?> <?= e(BRAND_NAME) ?>. All rights reserved.</small>
          </aside>

          <main class="auth__main">
            <div class="auth__card card card--pad-lg">
              <h2>Create your account</h2>
              <p class="muted mt-0" style="margin-top:2px;">A few details and you're in. We'll send a code to verify your email.</p>

              <?php foreach ($flashes as $f): ?>
                <div class="alert alert--<?= e($f['type']) ?>" role="status">
                  <span><?= e($f['message']) ?></span>
                </div>
              <?php endforeach; ?>

              <form method="post" action="/user/signup.php" autocomplete="on" novalidate>
                <?= csrf_field() ?>
                <div class="form-grid mt-4">
                  <div class="field">
                    <label for="name">Full name</label>
                    <input class="input" id="name" name="name" type="text" autocomplete="name"
                           placeholder="Juan dela Cruz" value="<?= e($name) ?>" required>
                  </div>
                  <div class="field">
                    <label for="email">Email address</label>
                    <input class="input" id="email" name="email" type="email" autocomplete="email"
                           placeholder="you@example.com" value="<?= e($email) ?>" required>
                  </div>
                  <div class="field">
                    <label for="password">Password</label>
                    <div class="input-wrap">
                      <input class="input" id="password" name="password" type="password" autocomplete="new-password"
                             placeholder="At least 8 characters" required>
                    </div>
                    <span class="hint">Use 8 characters or more — mix in numbers for strength.</span>
                  </div>
                  <div class="form-actions">
                    <button class="btn btn--gold btn--lg btn--block" type="submit">Create account</button>
                  </div>
                </div>
              </form>

              <?php if ($googleConfigured): ?>
                <div class="divider"></div>
                <div class="t-center muted">or continue with</div>
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
              <?php endif; ?>

              <p class="auth__switch">
                Already have an account? <a href="/user/login.php">Sign in</a>
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
