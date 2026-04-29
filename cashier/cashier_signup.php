<?php session_start(); ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../styles.css">
<title>Cashier Sign Up</title>
</head>

<body class="cashier-auth-body">

<div class="split-container">

    <div class="form-side">

        <div class="auth-card">

            <h1 class="auth-title">Cashier Sign Up</h1>
            <p class="auth-subtitle">Create your account</p>

            <form method="POST" action="cashier_process.php">

                <input type="hidden" name="action" value="signup">

                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-input" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-input" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-input" required>
                </div>

                <button type="submit" class="btn-auth">Sign Up</button>

            </form>

            <p class="auth-footer">
                Already have an account? <a href="cashier_login.php">Login</a>
            </p>

        </div>

    </div>

    <div class="visual-side">
        
    </div>
</div>

</body>
</html>