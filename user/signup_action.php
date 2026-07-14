<?php
session_start();
include("../config.php");
include("../firebaseRDB.php");

$otp = trim($_POST['otp'] ?? '');

// Must have OTP and pending signup in session
if ($otp === '' || !isset($_SESSION['otp'])) {
    header("Location: sign_up.php");
    exit;
}

// Verify OTP
if ($otp != $_SESSION['otp']) {
    header("Location: verify_otp.php?error=invalid");
    exit;
}

// Check expiry
if (time() > $_SESSION['otp_expires']) {
    // Clear stale OTP
    unset($_SESSION['otp'], $_SESSION['otp_expires'],
          $_SESSION['otp_name'], $_SESSION['otp_email'],
          $_SESSION['otp_password']);
    header("Location: verify_otp.php?error=expired");
    exit;
}

// Read signup data from session
$name = $_SESSION['otp_name'];
$email = $_SESSION['otp_email'];
$password = $_SESSION['otp_password'];

// Hash password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

try {
    $rdb = new firebaseRDB($databaseURL);

    // Insert user into Firebase
    $insert = $rdb->insert("/user", [
        "name" => $name,
        "email" => $email,
        "password" => $hashedPassword
    ]);

    $result = json_decode($insert, true);

    // Clear OTP session data regardless of result
    unset($_SESSION['otp'], $_SESSION['otp_expires'],
          $_SESSION['otp_name'], $_SESSION['otp_email'],
          $_SESSION['otp_password']);

    if ($result != null) {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Signup Successful</title>
            <meta http-equiv="refresh" content="3;url=login.php">
            <style>
                body { font-family: Arial, sans-serif; text-align: center; margin-top: 100px; }
                .message { font-size: 20px; color: green; }
            </style>
        </head>
        <body>
            <p class="message">Sign up successful! Redirecting to login page...</p>
            <p>If you are not redirected, <a href="login.php">click here</a>.</p>
        </body>
        </html>
        <?php
        exit;
    } else {
        echo "Sign up failed";
    }

} catch(Exception $e){
    echo "Error: " . $e->getMessage();
}
