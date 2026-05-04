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

<body class="kitchen-auth-body">

<div class="split-container">

    <!-- LEFT FORM -->
    <div class="form-side">
        <div class="auth-card">

            <h1 class="auth-title">Welcome Back</h1>
            <p class="auth-subtitle">Login to continue to the kitchen</p>

            <form method="POST" action="kitchen_process.php">
                <input type="hidden" name="action" value="login">

                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-input" placeholder="Enter your email address" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-input" placeholder="Enter your password" required>
                </div>

                <button type="submit" class="btn-primary-auth">Login</button>
            </form>

            <p class="auth-footer">
                Don't have an account?
                <a href="kitchen_signup.php">Sign up</a>
            </p>

        </div>
    </div>

    <!-- RIGHT VISUAL -->
    <div class="visual-side">
        <div class="profile-circle">
           <img src="https://cdn-icons-png.flaticon.com/512/4140/4140048.png">
        </div>
    </div>

</div>

</body>
</html>