<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../styles.css">
<title>Kitchen Sign Up</title>
</head>
<body>

<div class="container-sm" style="padding-top: 80px;">
    <div class="auth-card">
        <h1 class="auth-title">Kitchen Sign Up</h1>
        <p class="auth-subtitle">Create your account</p>

        <form method="POST" action="kitchen_process.php">
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

            <button type="submit" class="btn btn-primary btn-block">Sign Up</button>
        </form>

        <p class="auth-footer">
            Already have an account? <a href="kitchen_login.php">Login</a>
        </p>
    </div>
</div>

</body>
</html>
