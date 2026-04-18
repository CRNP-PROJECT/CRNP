<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../styles.css">
    <title>Kitchen Sign Up | CRPN</title>
</head>

<style>
* {
    transition: all 0.2s ease-in-out;
}
</style>

<body class="kitchen-auth-body">

    <div class="kitchen-wrapper">
        <div class="split-container">
            
            <div class="visual-side">
                <div class="blurred-bg"></div>
            </div>

            <div class="form-side">
                <div class="auth-card">
                    <div class="auth-header">
                        <h1 class="auth-title">Staff Signup</h1>
                        <p class="auth-subtitle">Join The Kitchen Staff Team</p>
                    </div>

                    <form method="POST" action="kitchen_process.php">
                        <input type="hidden" name="action" value="signup">

                        <div class="form-group">
                            <label class="form-label">
                                <i class="fa-solid fa-id-card"></i> Full Name
                            </label>
                            <input type="text" name="full_name" class="form-input" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                <i class="fa-solid fa-envelope"></i> Email Address
                            </label>
                            <input type="email" name="email" class="form-input" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                <i class="fa-solid fa-lock"></i> Password
                            </label>
                            <input type="password" name="password" class="form-input" required>
                        </div>

                        <button type="submit" class="btn-primary-auth">
                            Sign Up <i class="fa-solid fa-user-plus"></i>
                        </button>
                    </form>

                    <p class="auth-footer">
                        Already have an account? <a href="kitchen_login.php">Login</a>
                    </p>
                </div>
            </div>

        </div>
    </div>

</body>
</html>