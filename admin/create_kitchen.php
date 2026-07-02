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
    <title>Create Kitchen Staff</title>
    <!-- Brand Asset Favicon & External Vector Graphic Fonts -->
    <link rel="icon" href="../images/profile.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../style.css">
</head>

<body class="kitchen-auth-body">

<div class="split-container">

    <!-- Left Panel: Core Data Entry Workspace -->
    <div class="form-side">
        <div class="auth-card">

            <h1 class="auth-title">Create Kitchen Staff</h1>
            <p class="auth-subtitle">Admin Only Account Creation</p>

            <form method="POST" action="admin_process.php">
                <!-- Action Route Identifier Token -->
                <input type="hidden" name="action" value="admin_create_kitchen">

                <!-- Full Name Field Component with Vector Icon -->
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <div class="input-icon-wrapper">
                        <i class="fa-solid fa-user input-vector-icon"></i>
                        <input type="text" 
                               name="full_name" 
                               class="form-input" 
                               placeholder="Enter full name" 
                               required>
                    </div>
                </div>

                <!-- Email Address Field Component with Vector Icon -->
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

                <!-- Security Password Field Component with Vector Icon -->
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <div class="input-icon-wrapper">
                        <i class="fa-solid fa-lock input-vector-icon"></i>
                        <input type="password" 
                               name="password" 
                               class="form-input" 
                               placeholder="Create password" 
                               required>
                    </div>
                </div>

                <!-- Core Form Dispatch Call to Action Trigger -->
                <button type="submit" class="btn-primary-auth">
                    Create Account <i class="fa-solid fa-user-plus btn-icon-offset"></i>
                </button>
            </form>

            <!-- Dashboard Framework Return Portal Link -->
            <p class="auth-footer">
                <a href="admin_index.php">
                    <i class="fa-solid fa-arrow-left"></i> Back to Admin Dashboard
                </a>
            </p>

        </div>
    </div>

    <!-- Right Panel: Brand Atmosphere Presentation Space -->
    <div class="brand-side">
        <div class="brand-overlay"></div>
        <div class="brand-content-wrapper">
            <span class="brand-badge">CULINARY PRODUCTION</span>
            <h2 class="brand-heading">Crates N' Plates</h2>
            <p class="brand-tagline">Exquisite dining administration, premium high-density hospitality management panels.</p>
        </div>
    </div>

</div>

</body>
</html>