<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Login | Crates N' Plates</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../styles.css">
</head>

<body class="user-login-bg">

<div class="user-login-wrapper">
    <div class="user-login-card">

        <h1 class="user-login-title">Welcome Back</h1>
        <p class="user-login-subtitle">Sign in to your account</p>

        <!-- NORMAL LOGIN ONLY -->
        <form method="POST" action="login_action.php">

            <div class="user-login-group">
                <i class="fa fa-envelope"></i>
                <input type="email" name="email" placeholder="Email" required>
            </div>

            <div class="user-login-group">
                <i class="fa fa-lock"></i>
                <input type="password" name="password" placeholder="Password" required>
            </div>

            <button type="submit" class="user-login-btn">
                Login
            </button>

        </form>

        <p class="user-login-link">
            Don't have an account? <a href="sign_up.php">Sign Up</a>
        </p>

    </div>
</div>

</body>
</html>