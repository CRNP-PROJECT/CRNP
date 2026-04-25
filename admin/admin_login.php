<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login</title>
<link rel="stylesheet" href="css/style.css">
</head>

<body class="admin-auth-body">

<div class="admin-auth-container">

    <div class="admin-auth-card">

        <h1 class="admin-auth-title">Admin Login</h1>
        <p class="admin-auth-subtitle">Sign in to admin panel</p>

        <?php if(isset($_GET['success'])): ?>
            <div class="admin-auth-alert admin-auth-alert-success">
                Admin account created! Please login.
            </div>
        <?php endif; ?>

        <?php if(isset($_GET['error'])): ?>
            <div class="admin-auth-alert admin-auth-alert-error">
                <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>

        <form action="admin_B.php" method="POST">

            <input type="email" name="email" placeholder="Email" required>

            <input type="password" name="password" placeholder="Password" required>

            <button type="submit" name="login">Login</button>

        </form>

        <div class="admin-auth-link">
            <p>Don’t have an account? <a href="admin_signup.php">Sign up</a></p>
        </div>

    </div>

</div>

</body>
</html>