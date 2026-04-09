<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="css/style.css">
<title>Admin Login</title>
</head>
<body>

<div class="container-sm" style="padding-top: 80px;">
    <div class="auth-card">
        <h1 class="auth-title">Admin Login</h1>
        <p class="auth-subtitle">Sign in to admin panel</p>

        <?php if(isset($_GET['success'])): ?>
            <div class="alert alert-success">Admin account created! Please login.</div>
        <?php endif; ?>

        <?php if(isset($_GET['error'])): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>

        <form action="admin_B.php" method="POST">
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-input" required>
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-input" required>
            </div>

            <button type="submit" name="login" class="btn btn-primary btn-block">Login</button>
        </form>
    </div>
</div>

</body>
</html>
