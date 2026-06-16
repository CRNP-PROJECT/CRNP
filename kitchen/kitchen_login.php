<?php
session_start();
if(isset($_SESSION['kitchen_email'])){
    header("Location: kitchen_index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="icon" href="../images/profile.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../styles.css">

    <title>Kitchen Login</title>
</head>

<body class="kitchen-login-body">

<div class="kitchen-login-container">
    <div class="kitchen-login-card">
        <?php if (isset($_GET['error'])): ?>
            <div class="kitchen-login-error-notif">
                <?php 
                    if ($_GET['error'] === 'empty') {
                        echo "All fields are required.";
                    } else {
                        echo "Invalid email or password.";
                    }
                ?>
            </div>
        <?php endif; ?>

        <h1 class="kitchen-login-title">Welcome Back</h1>
        <p class="kitchen-login-subtitle">Login to continue to the kitchen</p>

        <form method="POST" action="kitchen_process.php">
            <input type="hidden" name="action" value="login">

            <div class="kitchen-login-group">
                <label class="kitchen-login-label">Email Address</label>
                <input type="email" name="email" class="kitchen-login-input" placeholder="Enter your email address" required>
            </div>

            <div class="kitchen-login-group">
                <label class="kitchen-login-label">Password</label>
                <input type="password" name="password" class="kitchen-login-input" placeholder="Enter your password" required>
            </div>

            <button type="submit" class="kitchen-login-btn">Login</button>
        </form>

    </div>
</div>

</body>
</html>