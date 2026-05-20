<?php
session_start();

// ONLY ADMIN CAN ACCESS THIS PAGE
if(!isset($_SESSION['admin_email'])){
    header("Location: ../admin_login.php");
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

    <title>Create Kitchen Staff</title>
</head>

<body class="kitchen-auth-body">

<div class="split-container">

    <!-- LEFT FORM -->
    <div class="form-side">
        <div class="auth-card">

            <h1 class="auth-title">Create Kitchen Staff</h1>
            <p class="auth-subtitle">Admin Only Account Creation</p>

            <form method="POST" action="admin_process.php">
                <input type="hidden" name="action" value="admin_create_kitchen">

                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-input" placeholder="Enter full name" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-input" placeholder="Enter email address" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-input" placeholder="Create password" required>
                </div>

                <button type="submit" class="btn-primary-auth">Create Account</button>
            </form>

            <p class="auth-footer">
                Go back to
                <a href="admin_index.php">Admin Dashboard</a>
            </p>

        </div>
    </div>

    

</div>

</body>
</html>