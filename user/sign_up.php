<?php require_once __DIR__ . '/../config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Sign Up | Crates N' Plates</title>

<!-- GOOGLE SIGN-IN -->
<script src="https://accounts.google.com/gsi/client" async defer></script>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../styles.css">
<style>
.signup-error { background: #fef2f2; color: #d32f2f; padding: 10px 14px; border-radius: 8px; font-size: 14px; margin-bottom: 16px; text-align: center; }
</style>
</head>

<body class="signup-body">

    <?php
    $error = $_GET['error'] ?? '';
    $err_msgs = [
        'required' => 'All fields are required.',
        'password_short' => 'Password must be at least 8 characters.',
        'invalid_email' => 'Invalid email format.',
        'email_taken' => 'Email already registered.',
    ];
    $err_msg = $err_msgs[$error] ?? '';
    ?>

    <!-- GOOGLE CONFIG -->
    <div id="g_id_onload"
        data-client_id="<?= htmlspecialchars($_ENV['GOOGLE_CLIENT_ID']) ?>"
        data-callback="handleCredentialResponse">
    </div>

    <div class="signup-container">

        <div class="signup-card">

            <div class="signup-welcome">
                <h1>Join Us</h1>
                <p>
                    Create your account and experience the flavors of
                    Crates N' Plates.
                </p>
            </div>

            <div class="signup-form-side">

                <div class="signup-form-card">

                    <h2 class="signup-brand">
                        CRATES N' PLATES
                    </h2>

                    <h3 class="signup-title">
                        Create Account
                    </h3>

                    <p class="signup-subtitle">
                        Join us today
                    </p>

                    <form method="POST" action="send_otp.php">

                        <?php if ($err_msg): ?>
                            <div class="signup-error"><?= htmlspecialchars($err_msg) ?></div>
                        <?php endif; ?>

                        <div class="signup-group">
                            <label class="signup-label">
                                Full Name
                            </label>

                            <input
                                type="text"
                                name="name"
                                class="signup-input"
                                placeholder="Enter your name"
                                required>
                        </div>

                        <div class="signup-group">
                            <label class="signup-label">
                                Email Address
                            </label>

                            <input
                                type="email"
                                name="email"
                                class="signup-input"
                                placeholder="Enter your email"
                                required>
                        </div>

                        <div class="signup-group">
                            <label class="signup-label">
                                Password
                            </label>

                            <input
                                type="password"
                                name="password"
                                class="signup-input"
                                placeholder="Enter your password"
                                required>
                        </div>

                        <button
                            type="submit"
                            class="signup-btn">
                            Sign Up
                        </button>

                        <div class="signup-divider">
                            <span>or</span>
                        </div>

                        <div class="signup-google">
                            <div class="g_id_signin"
                                data-type="standard"
                                data-size="large"
                                data-text="signup">
                            </div>
                        </div>

                    </form>

                    <p class="signup-footer">
                        Already have an account?
                        <a href="login.php">Login</a>
                    </p>

                </div>

            </div>

        </div>

    </div>

<!-- GOOGLE CALLBACK SCRIPT -->
<script>
function handleCredentialResponse(response) {

    const form = document.createElement("form");
    form.method = "POST";
    form.action = "google_login.php";

    const input = document.createElement("input");
    input.type = "hidden";
    input.name = "token";
    input.value = response.credential;

    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
}
</script>

</body>
</html>