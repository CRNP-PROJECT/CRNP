<?php

namespace App\Controllers\User;

/**
 * VerifyOtpController — confirms a 6-digit OTP for a pending customer signup.
 * Supports ?resend=1&email=... to regenerate and re-email a fresh code.
 */
class VerifyOtpController {

    public static function render(): void {
        $email = trim((string) ($_GET['email'] ?? post('email', '')));
        $email = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';

        $isResend = isset($_GET['resend']) && $_GET['resend'] === '1';

        // ---------- Resend branch ----------
        if ($isResend) {
            if ($email === '') {
                flash('We couldn\'t find that email. Please sign up again.', 'danger');
                redirect('/user/signup.php');
            }
            $db        = getDB();
            $existing  = filter_by(rows($db->retrieve('/user')), 'email', $email);
            if (!$existing) {
                flash('We couldn\'t find that email. Please sign up again.', 'danger');
                redirect('/user/signup.php');
            }
            $id   = (string) array_key_first($existing);
            $user = reset($existing);

            if (!empty($user['email_verified'])) {
                flash('Your email is already verified — please sign in.', 'info');
                redirect('/user/login.php');
            }

            // Rate limit: 3 OTP sends per 15 minutes per email.
            if (!rate_limit('signup_otp_' . $email, 3, 900)) {
                flash('Too many code requests for that email. Please try again in 15 minutes.', 'danger');
                redirect('/user/verify_otp.php?email=' . urlencode($email));
            }

            $otp     = gen_otp();
            $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

            try {
                $db->update('/user', $id, ['otp' => $otp, 'otp_expires' => $expires]);
            } catch (Throwable $e) {
                flash('Could not issue a new code. Please try again shortly.', 'danger');
                redirect('/user/verify_otp.php?email=' . urlencode($email));
            }

            $sent = sendOTP($email, $otp);
            if (!$sent) {
                // P0: never leak the OTP in production. Only surface the dev
                // fallback when the host has explicitly opted into DEV_MODE.
                if (defined('DEV_MODE') && DEV_MODE) {
                    flash('SMTP not configured — new OTP is ' . $otp . ' (dev only).', 'warn');
                } else {
                    flash('Could not send verification email. Please try again or contact support.', 'danger');
                }
            } else {
                flash('A new code was sent to ' . $email . '.', 'info');
            }
            redirect('/user/verify_otp.php?email=' . urlencode($email));
        }

        // ---------- Verify branch ----------
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $otp = trim((string) post('otp', ''));
            $errors = [];

            if ($email === '') {
                $errors[] = 'Missing email. Please sign up again.';
            }
            if (strlen($otp) === 0) {
                $errors[] = 'Please enter the 6-digit code.';
            }

            $user = null;
            $userId = null;
            if (!$errors) {
                $db       = getDB();
                $existing = filter_by(rows($db->retrieve('/user')), 'email', $email);
                if (!$existing) {
                    $errors[] = 'We couldn\'t find that account. Please sign up again.';
                } else {
                    $userId = (string) array_key_first($existing);
                    $user   = reset($existing);
                }
            }

            if (!$errors) {
                $validOtp     = isset($user['otp']) && hash_equals((string) $user['otp'], $otp);
                $notExpired   = !empty($user['otp_expires']) && strtotime($user['otp_expires']) > time();
                if ($validOtp && $notExpired) {
                    try {
                        $db = getDB();
                        $db->update('/user', $userId, [
                            'email_verified' => true,
                            'otp'            => null,
                            'otp_expires'    => null,
                        ]);
                    } catch (Throwable $e) {
                        $errors[] = 'Could not verify your email. Please try again.';
                    }
                } else {
                    $errors[] = 'Invalid or expired code. Please try again or resend a new one.';
                }
            }

            if (!$errors) {
                flash('Email verified — please sign in.', 'ok');
                redirect('/user/login.php');
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
          <title>Verify your email &middot; <?= e(BRAND_NAME) ?></title>
          <meta name="description" content="<?= e(BRAND_NAME) ?> — <?= e(BRAND_TAGLINE) ?>">
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
            <a class="brand" href="/user/login.php">
              <span class="brand__mark"><img src="/assets/img/logo.png" alt="CRATES N' PLATES" class="brand__logo"></span>
              <span>
                <span class="brand__name"><?= e(BRAND_NAME) ?></span><br>
                <span class="brand__tag"><?= e(BRAND_TAGLINE) ?></span>
              </span>
            </a>

            <div>
              <span class="eyebrow" style="font-size:12px;letter-spacing:.22em;text-transform:uppercase;color:var(--gold);">One last step</span>
              <h1>Let's confirm it's really you.</h1>
              <p>We've sent a 6-digit verification code to your inbox. Codes expire in 10 minutes — keep this window open while you check your email.</p>
            </div>

            <small style="color:#9c8f7b;">&copy; <?= date('Y') ?> <?= e(BRAND_NAME) ?>. All rights reserved.</small>
          </aside>

          <main class="auth__main">
            <div class="auth__card card card--pad-lg">
              <h2>Enter verification code</h2>
              <p class="muted mt-0" style="margin-top:2px;">
                Sent to <strong style="color:var(--ink);"><?= e($email ?: 'your email') ?></strong>.
              </p>

              <?php foreach ($flashes as $f): ?>
                <div class="alert alert--<?= e($f['type']) ?>" role="status">
                  <span><?= e($f['message']) ?></span>
                </div>
              <?php endforeach; ?>

              <form method="post" action="/user/verify_otp.php" autocomplete="one-time-code" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="email" value="<?= e($email) ?>">
                <div class="form-grid mt-4">
                  <div class="field">
                    <label for="otp">6-digit code</label>
                    <input class="input" id="otp" name="otp" type="text" inputmode="numeric"
                           pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code"
                           placeholder="000000" style="text-align:center;letter-spacing:.6em;font-size:1.2rem;"
                           value="<?= e(post('otp', '')) ?>" required>
                    <span class="hint">Check your spam folder if you don't see it within a minute.</span>
                  </div>
                  <div class="form-actions">
                    <button class="btn btn--gold btn--lg btn--block" type="submit">Verify email</button>
                  </div>
                </div>
              </form>

              <div class="row row--between mt-4" style="font-size:14px;">
                <a class="muted" href="/user/signup.php">Use a different email</a>
                <a href="/user/verify_otp.php?resend=1&email=<?= e(urlencode($email)) ?>">Resend code</a>
              </div>

              <p class="auth__switch">
                Already verified? <a href="/user/login.php">Sign in</a>
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
