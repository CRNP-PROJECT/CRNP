<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Sign Up | Crates N' Plates</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../styles.css">
</head>

<body class="user-signup-bg">

<div class="user-signup-wrapper">
    <div class="user-signup-card">

        <h1 class="user-signup-title">Create Account</h1>
        <p class="user-signup-subtitle">Join us today</p>

        <form method="POST" action="signup_action.php">

            <div class="user-signup-group">
                <i class="fa fa-user"></i>
                <!-- FIXED -->
                <input type="text" name="name" placeholder="Full Name" required>
            </div>

            <div class="user-signup-group">
                <i class="fa fa-envelope"></i>
                <!-- FIXED -->
                <input type="email" name="email" placeholder="Email" required>
            </div>

            <div class="user-signup-group">
                <i class="fa fa-lock"></i>
                <!-- FIXED -->
                <input type="password" name="password" placeholder="Password" required>
            </div>

            <button type="submit" class="user-signup-btn">
                Sign Up
            </button>

        </form>

        <p class="user-signup-link">
            Already have an account? <a href="login.php">Login</a>
        </p>

    </div>
</div>

</body>
</html>