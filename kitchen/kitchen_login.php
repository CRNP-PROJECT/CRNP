<?php
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../styles.css">
    <title>Kitchen Login | CRPN</title>
</head>

<style>
* {
    transition: all 0.2s ease-in-out;
}
</style>

<body class="kitchen-auth-body">

    <div class="kitchen-wrapper">
        <div class="split-container">

            <!-- RIGHT VISUAL SIDE -->
            <div class="visual-side">
                <div class="socials">
                    <div></div>
                    <div></div>
                    <div></div>
                    <div></div>
                </div>
            </div>

            <!-- LEFT FORM SIDE -->
            <div class="form-side">
                <div class="auth-card">

                    <div class="auth-header">
                        <h1 class="auth-title">Welcome Back</h1>
                        <p class="auth-subtitle">Login to continue to the kitchen</p>
                    </div>

                    <form method="POST" action="kitchen_process.php">
                        <input type="hidden" name="action" value="login">

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
                            Login <i class="fa-solid fa-right-to-bracket"></i>
                        </button>
                    </form>

                    <p class="auth-footer">
                        Don't have an account? <a href="kitchen_signup.php">Sign up</a>
                    </p>

                </div>
            </div>

        </div>
    </div>

</body>
</html>