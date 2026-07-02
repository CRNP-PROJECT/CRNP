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
    <title>Admin Login - Crates N' Plates</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../style.css">
</head>
<body class="admin-auth-body">

<div class="admin-auth-container login-layout">

    <div class="admin-left-banner-side">
        <div class="admin-banner-overlay-content">
            <h2 class="admin-banner-display-title">Control Panel</h2>
            <p class="admin-banner-display-subtitle">Secure gateway for authorized administrative access to dashboard controls.</p>
        </div>
    </div>

    <div class="admin-form-side">
        <div class="admin-auth-card">

            <div class="admin-view-title-group">
                <h2 class="admin-auth-title">Admin Sign In</h2>
                <p class="admin-auth-subtitle">Verify your administrator credentials to continue</p>
            </div>

            <?php if(isset($_GET['success'])): ?>
                <div class="admin-auth-alert admin-auth-alert-success">
                    <i class="fa-solid fa-circle-check"></i> Admin account created! Please login.
                </div>
            <?php endif; ?>

            <?php if(isset($_GET['error'])): ?>
                <div class="admin-auth-alert admin-auth-alert-error">
                    <i class="fa-solid fa-circle-exclamation"></i> <?php echo htmlspecialchars($_GET['error']); ?>
                </div>
            <?php endif; ?>

            <form action="admin_B.php" method="POST">

                <div class="admin-form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="admin-form-input" placeholder="Enter admin email address" required>
                </div>

                <div class="admin-form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="admin-form-input" placeholder="Enter account password" required>
                </div>

                <button type="submit" name="login" class="admin-action-submit-btn">Authorize & Login</button>

            </form>

            <div class="admin-auth-link">
                <p>Need a staff profile? <a href="admin_signup.php">Register admin</a></p>
            </div>

        </div>
    </div>

</div>

</body>
</html>