<?php
session_start();

// No OTP in session → redirect back to signup
if (!isset($_SESSION['otp'])) {
    header("Location: sign_up.php");
    exit;
}

$email = htmlspecialchars($_SESSION['otp_email'] ?? '');
$expires_at = $_SESSION['otp_expires'] ?? 0;
$is_expired = time() > $expires_at;
$resend_at = $_SESSION['otp_resend_at'] ?? 0;
$cooldown = $resend_at - time();
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verify OTP | Crates N' Plates</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../styles.css">
<style>
.otp-body { background: #f8f6f2; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: Inter, sans-serif; }
.otp-card { background: #fff; padding: 40px; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,.08); max-width: 420px; width: 100%; text-align: center; }
.otp-card h1 { font-size: 24px; margin-bottom: 8px; color: #1a1a1a; }
.otp-card p { color: #666; margin-bottom: 24px; font-size: 14px; }
.otp-input { font-size: 28px; letter-spacing: 8px; text-align: center; padding: 12px; width: 100%; border: 2px solid #ddd; border-radius: 10px; outline: none; box-sizing: border-box; font-weight: 600; }
.otp-input:focus { border-color: #a67c52; }
.otp-btn { background: #1a1a1a; color: #fff; border: none; padding: 14px; border-radius: 10px; font-size: 16px; font-weight: 600; width: 100%; cursor: pointer; margin-top: 16px; }
.otp-btn:hover { background: #333; }
.otp-error { color: #d32f2f; font-size: 14px; margin-bottom: 12px; }
.otp-resend { margin-top: 20px; font-size: 13px; color: #999; }
.otp-resend a { color: #a67c52; text-decoration: none; }
.otp-email { font-weight: 600; color: #1a1a1a; }
</style>
</head>
<body class="otp-body">

<div class="otp-card">
    <h1>Verify Email</h1>
    <p>Enter the 6-digit code sent to<br><span class="otp-email"><?= $email ?></span></p>

    <?php if ($error === 'expired'): ?>
        <div class="otp-error">OTP expired. Request a new one.</div>
    <?php elseif ($error === 'invalid'): ?>
        <div class="otp-error">Invalid OTP. Try again.</div>
    <?php elseif ($error === 'cooldown'): ?>
        <div class="otp-error">Please wait before resending.</div>
    <?php endif; ?>

    <?php if ($is_expired): ?>
        <div class="otp-error">This code has expired.</div>
        <div class="otp-resend"><a href="send_otp.php?resend=1">Resend OTP</a></div>
    <?php elseif ($cooldown > 0): ?>
        <form method="POST" action="signup_action.php">
            <input type="text" name="otp" class="otp-input" maxlength="6" inputmode="numeric" pattern="[0-9]{6}" placeholder="000000" required autocomplete="off">
            <button type="submit" class="otp-btn">Verify & Create Account</button>
        </form>
        <div class="otp-resend">Resend in <span id="otp-cooldown"><?= $cooldown ?></span>s</div>
        <script>
        (function(){
            var el = document.getElementById('otp-cooldown');
            var s = parseInt(el.textContent, 10);
            if (isNaN(s)) return;
            var t = setInterval(function(){
                s--;
                if (s <= 0) { clearInterval(t); location.reload(); }
                else el.textContent = s;
            }, 1000);
        })();
        </script>
    <?php else: ?>
        <form method="POST" action="signup_action.php">
            <input type="text" name="otp" class="otp-input" maxlength="6" inputmode="numeric" pattern="[0-9]{6}" placeholder="000000" required autocomplete="off">
            <button type="submit" class="otp-btn">Verify & Create Account</button>
        </form>
        <div class="otp-resend">
            Didn't receive it? <a href="send_otp.php?resend=1">Resend OTP</a>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
