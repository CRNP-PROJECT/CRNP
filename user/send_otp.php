<?php
session_start();
include("../config.php");
include("../firebaseRDB.php");

// Mailer is optional — fails gracefully if vendor/autoload.php missing
$mailerLoaded = false;
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    include("../mailer.php");
    $mailerLoaded = true;
}

// Resend: reuse data already in session
if (isset($_GET['resend']) && isset($_SESSION['otp_email'])) {
    // Cooldown check
    $cooldown = ($_SESSION['otp_resend_at'] ?? 0) - time();
    if ($cooldown > 0) {
        header("Location: verify_otp.php?error=cooldown&wait=$cooldown");
        exit;
    }
    $name = $_SESSION['otp_name'];
    $email = $_SESSION['otp_email'];
    $password = $_SESSION['otp_password'];
} else {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validate
    if ($name === '' || $email === '' || $password === '') {
        die('All fields are required.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die('Invalid email format.');
    }

    // Check duplicate email
    $rdb = new firebaseRDB($databaseURL);
    $existing = json_decode($rdb->retrieve("/user", "email", "EQUAL", $email), true);
    if (is_array($existing) && count($existing) > 0) {
        die('Email already registered.');
    }
}

// Generate 6-digit OTP
$otp = random_int(100000, 999999);

// Store in session
$_SESSION['otp'] = $otp;
$_SESSION['otp_expires'] = time() + 300; // 5 minutes
$_SESSION['otp_resend_at'] = time() + 60; // 60s cooldown
$_SESSION['otp_name'] = $name;
$_SESSION['otp_email'] = $email;
$_SESSION['otp_password'] = $password;

// Send email
$subject = 'Your OTP Code — Crates N\' Plates';
$message = "
<html>
<body style='font-family: Arial, sans-serif; padding: 20px;'>
    <h2>Email Verification</h2>
    <p>Hi <strong>" . htmlspecialchars($name) . "</strong>,</p>
    <p>Use the OTP below to complete your sign-up:</p>
    <div style='font-size: 32px; font-weight: bold; letter-spacing: 6px;
                background: #f5f5f5; padding: 16px; text-align: center;
                border-radius: 8px; margin: 20px 0;'>"
        . $otp .
    "</div>
    <p>This code expires in 5 minutes.</p>
    <p>If you didn't request this, ignore this email.</p>
</body>
</html>";

// Send email (skip if mailer unavailable)
$sent = false;
if ($mailerLoaded) {
    $sent = sendNotification($email, $subject, $message);
}

// ponytail: OTP always stored in session regardless of mail send,
// so dev can check error_log / session if SMTP not configured yet.
if (!$sent) {
    error_log("OTP send to $email — OTP=$otp");
}

header("Location: verify_otp.php");
exit;
