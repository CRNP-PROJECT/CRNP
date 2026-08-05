<?php
/**
 * forgot_password.php — Step 1: Enter email, receive OTP to reset password.
 */
require_once __DIR__ . '/../init.php';
security_headers();

if (!empty($_SESSION['user_id'])) {
    redirect('/user/products.php');
}

$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = trim((string) post('email', ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('Please enter a valid email address.', 'danger');
        redirect('/user/forgot_password.php');
    }

    $rlKey = 'forgot_pw_' . $email;
    if (!rate_limit($rlKey, 3, 900)) {
        flash('Too many attempts. Please try again in 15 minutes.', 'danger');
        redirect('/user/forgot_password.php');
    }

    $db       = getDB();
    $existing = filter_by(rows($db->retrieve('/user')), 'email', $email);

    if (!$existing) {
        flash('No account found with that email.', 'danger');
        redirect('/user/forgot_password.php');
    }

    $userId = (string) array_key_first($existing);
    $otp    = gen_otp();
    $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    try {
        $db->update('/user', $userId, [
            'reset_otp'       => $otp,
            'reset_otp_expires' => $expires,
        ]);
    } catch (Throwable $e) {
        flash('Could not process your request. Please try again.', 'danger');
        redirect('/user/forgot_password.php');
    }

    $sent = sendOTP($email, $otp);
    if (!$sent) {
        if (defined('DEV_MODE') && DEV_MODE) {
            flash('SMTP not configured — OTP is ' . $otp . ' (dev only).', 'warn');
        } else {
            flash('Could not send email. Please try again or contact support.', 'danger');
        }
    } else {
        flash('We sent a 6-digit code to ' . $email . '.', 'info');
    }
    redirect('/user/reset_password.php?email=' . urlencode($email));
}

$flashes = get_flashes();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Forgot password &middot; <?= e(BRAND_NAME) ?></title>
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
      <span class="eyebrow" style="font-size:12px;letter-spacing:.22em;text-transform:uppercase;color:var(--gold);">Need help?</span>
      <h1>Forgot your password?</h1>
      <p>No worries — enter your email and we'll send you a code to reset it.</p>
    </div>

    <small style="color:#9c8f7b;">&copy; <?= date('Y') ?> <?= e(BRAND_NAME) ?>. All rights reserved.</small>
  </aside>

  <main class="auth__main">
    <div class="auth__card card card--pad-lg">
      <h2>Reset your password</h2>
      <p class="muted mt-0" style="margin-top:2px;">Enter the email address tied to your account.</p>

      <?php foreach ($flashes as $f): ?>
        <div class="alert alert--<?= e($f['type']) ?>" role="status">
          <span><?= e($f['message']) ?></span>
        </div>
      <?php endforeach; ?>

      <form method="post" action="/user/forgot_password.php" autocomplete="on" novalidate>
        <?= csrf_field() ?>
        <div class="form-grid mt-4">
          <div class="field">
            <label for="email">Email address</label>
            <input class="input" id="email" name="email" type="email" autocomplete="email"
                   placeholder="you@example.com" value="<?= e($email) ?>" required>
          </div>
          <div class="form-actions">
            <button class="btn btn--gold btn--lg btn--block" type="submit">Send reset code</button>
          </div>
        </div>
      </form>

      <p class="auth__switch">
        Remember your password? <a href="/user/login.php">Sign in</a>
      </p>
    </div>
  </main>
</div>
<script src="/assets/js/app.js"></script>
</body>
</html>
