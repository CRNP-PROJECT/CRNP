<?php session_start(); ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../styles.css">
<title>Cashier Login</title>
</head>

<body class="cashier-auth-body">

<div class="split-container">

    <div class="form-side">
        <div class="auth-card">

            <h1 class="auth-title">Cashier Login</h1>
            <p class="auth-subtitle">Sign in to your account</p>

            <form method="POST" action="cashier_process.php">

                <input type="hidden" name="action" value="login">

                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-input" placeholder="Enter your email address" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-input" placeholder="Enter your password" required>
                </div>

                <button type="submit" class="btn-auth">Login</button>

            </form>

            

        </div>

    </div>

    <div class="visual-side">
        <div class="profile-circle">
           <img src="https://cdn-icons-png.flaticon.com/512/4140/4140048.png">
        </div>
    </div>

</div>

</body>
</html>