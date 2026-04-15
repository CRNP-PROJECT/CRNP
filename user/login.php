<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Crates N' Plates</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../styles.css">
</head>
<body class="login-body">

<div class="container-sm">
    <div class="auth-card">
        <h1 class="auth-title">Welcome Back</h1>

        <form method="POST" action="login_action.php">
            <div class="form-group">
                <label class="form-label">
                    <i class="fa-solid fa-envelope"></i> Email
                </label>
                <input type="email" name="email" class="form-input" required placeholder="Enter your email">
            </div>

            <div class="form-group">
                <label class="form-label">
                    <i class="fa-solid fa-lock"></i> Password
                </label>
                <input type="password" name="password" class="form-input" required placeholder="Enter your password">
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                Login <i class=" "></i>
            </button>
        </form>

        <p class="auth-footer">
            Don't have an account? <a href="sign_up.php">Sign Up</a>
        </p>
    </div>
</div>

</body>
</html>