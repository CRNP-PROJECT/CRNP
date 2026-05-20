<?php
session_start();

if(!isset($_SESSION['admin_email'])){
    header("Location: admin_login.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../styles.css">
<title>Create Cashier Account</title>
</head>

<body class="cashier-auth-body">

<div class="split-container">

    <div class="form-side">

        <div class="auth-card">

            <h1 class="auth-title">Create Cashier</h1>
            <p class="auth-subtitle">Admin creates cashier account</p>

            <form method="POST" action="admin_process.php">

                <!-- IMPORTANT -->
                <input type="hidden"
                       name="create_cashier"
                       value="1">

                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text"
                           name="full_name"
                           class="form-input"
                           placeholder="Enter cashier name"
                           required>
                </div>

                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email"
                           name="email"
                           class="form-input"
                           placeholder="Enter email address"
                           required>
                </div>

                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password"
                           name="password"
                           class="form-input"
                           placeholder="Enter password"
                           required>
                </div>

                <button type="submit" class="btn-auth">
                    Create Account
                </button>

            </form>

            <p class="auth-footer">
                <a href="admin_index.php">Back to Management</a>
            </p>

        </div>

    </div>

    

</div>

</body>
</html>