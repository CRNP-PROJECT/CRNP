<?php
session_start();
if(isset($_SESSION['admin_email'])){
    header("Location: admin_index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login</title>

<!-- Google Font -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">

<!-- Icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<!-- CSS -->
<link rel="stylesheet" href="../style.css">
</head>

<body class="admin-auth-body">

<div class="admin-auth-container">

    <!-- LEFT SIDE -->
    <div class="admin-form-side">
        <div class="admin-auth-card">

            <h1 class="admin-auth-title">Welcome Back</h1>
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

                <div class="admin-form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="admin-form-input" placeholder="Enter your email" required>
                </div>

                <div class="admin-form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="admin-form-input" placeholder="Enter your password" required>
                </div>

                <button type="submit" name="login">Login</button>

            </form>

            <div class="admin-auth-link">
                <p>Don’t have an account? <a href="admin_signup.php">Sign up</a></p>
            </div>

        </div>
    </div>

</div>

</body>
</html>