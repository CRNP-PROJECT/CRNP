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

    <title>Kitchen Signup</title>
</head>

<body class="kitchen-auth-body">

<div class="split-container">

    <!-- LEFT FORM -->
    <div class="form-side">
        <div class="auth-card">

            <h1 class="auth-title">Staff Signup</h1>
            <p class="auth-subtitle">Join The Kitchen Staff Team</p>

            <form method="POST" action="kitchen_process.php">
                <input type="hidden" name="action" value="signup">

                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-input" placeholder="Enter your name" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-input" placeholder="Enter your email address" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-input" placeholder="Enter your password" required>
                </div>

                <button type="submit" class="btn-primary-auth">Sign Up</button>
            </form>

            <p class="auth-footer">
                Already have an account?
                <a href="kitchen_login.php">Login</a>
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