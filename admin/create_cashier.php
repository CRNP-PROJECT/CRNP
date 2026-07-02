<?php
session_start();

if(!isset($_SESSION['admin_email'])){
    header("Location: admin_login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Cashier Account</title>
    <!-- Premium Fonts & Icon Vectors -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../style.css">
</head>

<body class="cashier-auth-body">

<div class="split-container">

    <!-- Left Panel: Brand Atmosphere Presentation Space -->
    <div class="brand-side">
        <div class="brand-overlay"></div>
        <div class="brand-content-wrapper">
            <span class="brand-badge">MANAGEMENT TERMINAL</span>
            <h2 class="brand-heading">Crates N' Plates</h2>
            <p class="brand-tagline">Exquisite dining administration, premium high-density hospitality management panels.</p>
        </div>
    </div>

    <!-- Right Panel: Core Form Interactivity Grid -->
    <div class="form-side">
        <div class="auth-card">
            <h1 class="auth-title">Create Cashier</h1>
            <p class="auth-subtitle">Admin creates cashier account</p>

            <form method="POST" action="admin_process.php">
                <!-- Core Process Context Identifier -->
                <input type="hidden" name="create_cashier" value="1">

                <!-- Full Name Field Component Block -->
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <div class="input-icon-wrapper">
                        <i class="fa-solid fa-user input-vector-icon"></i>
                        <input type="text" 
                               name="full_name" 
                               class="form-input" 
                               placeholder="Enter cashier name" 
                               required>
                    </div>
                </div>

                <!-- Email Address Field Component Block -->
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <div class="input-icon-wrapper">
                        <i class="fa-solid fa-envelope input-vector-icon"></i>
                        <input type="email" 
                               name="email" 
                               class="form-input" 
                               placeholder="Enter email address" 
                               required>
                    </div>
                </div>

                <!-- Password Field Component Block -->
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <div class="input-icon-wrapper">
                        <i class="fa-solid fa-lock input-vector-icon"></i>
                        <input type="password" 
                               name="password" 
                               class="form-input" 
                               placeholder="Enter password" 
                               required>
                    </div>
                </div>

                <!-- Submissions Trigger Control Interface -->
                <button type="submit" class="btn-auth">
                    Create Account <i class="fa-solid fa-user-plus btn-icon-offset"></i>
                </button>
            </form>

            <!-- Interface System Structural Footer Link -->
            <p class="auth-footer">
                <a href="admin_index.php">
                    <i class="fa-solid fa-arrow-left"></i> Back to Management
                </a>
            </p>
        </div>
    </div>

</div>

</body>
</html>