<?php
/**
 * reset_password.php — Step 2: Enter OTP + new password to reset.
 * Supports resend via ?resend=1&email=...
 */
require_once __DIR__ . '/../init.php';
security_headers();

$email = trim((string) ($_GET['email'] ?? post('email', '')));
$email = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';

$isResend = isset($_GET['resend']) && $_GET['resend'] === '1';

// ---------- Resend branch ----------
if ($isResend) {
    if ($email === '') {
        flash('We couldn\'t find that email. Please try again.', 'danger');
        redirect('/user/forgot_password.php');
    }

    if (!rate_limit('forgot_pw_' . $email, 3, 900)) {
        flash('Too many attempts. Please try again in 15 minutes.', 'danger');
        redirect('/user/forgot_password.php');
    }

    $db       = getDB();
    $existing = filter_by(rows($db->retrieve('/user')), 'email', $email);
    if (!$existing) {
        flash('No account found with that email.', 'danger');
        redirect('/user/forgot_password.php');
    }

    $id      = (string) array_key_first($existing);
    $otp     = gen_otp();
    $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    try {
        $db->update('/user', $id, ['reset_otp' => $otp, 'reset_otp_expires' => $expires]);
    } catch (Throwable $e) {
        flash('Could not issue a new code. Please try again shortly.', 'danger');
        redirect('/user/reset_password.php?email=' . urlencode($email));
    }

    $sent = sendOTP($email, $otp);
    if (!$sent) {
        if (defined('DEV_MODE') && DEV_MODE) {
            flash('SMTP not configured — OTP is ' . $otp . ' (dev only).', 'warn');
        } else {
            flash('Could not send email. Please try again.', 'danger');
        }
    } else {
        flash('A new code was sent to ' . $email . '.', 'info');
    }
    redirect('/user/reset_password.php?email=' . urlencode($email));
}

// ---------- Reset branch ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $otp  = trim((string) post('otp', ''));
    $pw   = (string) post('password', '');
    $pw2  = (string) post('password_confirm', '');
    $errors = [];

    if ($email === '') {
        $errors[] = 'Missing email. Please start over from the login page.';
    }
    if (strlen($otp) === 0) {
        $errors[] = 'Please enter the 6-digit code.';
    }
    if (strlen($pw) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }
    if ($pw !== $pw2) {
        $errors[] = 'Passwords do not match.';
    }

    $user   = null;
    $userId = null;
    if (!$errors) {
        $db       = getDB();
        $existing = filter_by(rows($db->retrieve('/user')), 'email', $email);
        if (!$existing) {
            $errors[] = 'Account not found. Please start over.';
        } else {
            $userId = (string) array_key_first($existing);
            $user   = reset($existing);
        }
    }

    if (!$errors) {
        $validOtp   = isset($user['reset_otp']) && hash_equals((string) $user['reset_otp'], $otp);
        $notExpired = !empty($user['reset_otp_expires']) && strtotime($user['reset_otp_expires']) > time();
        if ($validOtp && $notExpired) {
            try {
                $db = getDB();
                $db->update('/user', $userId, [
                    'password_hash'     => password_hash($pw, PASSWORD_BCRYPT),
                    'reset_otp'         => null,
                    'reset_otp_expires' => null,
                ]);
            } catch (Throwable $e) {
                $errors[] = 'Could not reset your password. Please try again.';
            }
        } else {
            $errors[] = 'Invalid or expired code. Please try again or resend a new one.';
        }
    }

    if (!$errors) {
        flash('Password reset successful — please sign in with your new password.', 'ok');
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
  <title>Reset password &middot; <?= e(BRAND_NAME) ?></title>
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
      <span class="eyebrow" style="font-size:12px;letter-spacing:.22em;text-transform:uppercase;color:var(--gold);">Almost there</span>
      <h1>Set your new password.</h1>
      <p>Enter the 6-digit code we sent to your email, then choose a new password. Codes expire in 10 minutes.</p>
    </div>

    <small style="color:#9c8f7b;">&copy; <?= date('Y') ?> <?= e(BRAND_NAME) ?>. All rights reserved.</small>
  </aside>

  <main class="auth__main">
    <div class="auth__card card card--pad-lg">
      <h2>Enter code &amp; new password</h2>
      <p class="muted mt-0" style="margin-top:2px;">
        Sent to <strong style="color:var(--ink);"><?= e($email ?: 'your email') ?></strong>.
      </p>

      <?php foreach ($flashes as $f): ?>
        <div class="alert alert--<?= e($f['type']) ?>" role="status">
          <span><?= e($f['message']) ?></span>
        </div>
      <?php endforeach; ?>

      <form method="post" action="/user/reset_password.php" autocomplete="on" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="email" value="<?= e($email) ?>">
        <div class="form-grid mt-4">
          <div class="field">
            <label for="otp">6-digit code</label>
            <input class="input" id="otp" name="otp" type="text" inputmode="numeric"
                   pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code"
                   placeholder="000000" style="text-align:center;letter-spacing:.6em;font-size:1.2rem;" required>
          </div>
          <div class="field">
            <label for="password">New password</label>
            <input class="input" id="password" name="password" type="password" autocomplete="new-password"
                   placeholder="At least 8 characters" required minlength="8">
            <span class="hint">Use 8 characters or more.</span>
          </div>
          <div class="field">
            <label for="password_confirm">Confirm new password</label>
            <input class="input" id="password_confirm" name="password_confirm" type="password" autocomplete="new-password"
                   placeholder="Re-enter your password" required minlength="8">
          </div>
          <div class="form-actions">
            <button class="btn btn--gold btn--lg btn--block" type="submit">Reset password</button>
          </div>
        </div>
      </form>

      <div class="row row--between mt-4" style="font-size:14px;">
        <a class="muted" href="/user/forgot_password.php">Use a different email</a>
        <a href="/user/reset_password.php?resend=1&email=<?= e(urlencode($email)) ?>">Resend code</a>
      </div>

      <p class="auth__switch">
        Remember your password? <a href="/user/login.php">Sign in</a>
      </p>
    </div>
  </main>
</div>
<script src="/assets/js/app.js"></script>
</body>
</html>
