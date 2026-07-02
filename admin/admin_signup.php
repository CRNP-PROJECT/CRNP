<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Signup - Crates N' Plates</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../style.css">
</head>
<body class="admin-auth-body">

<div class="admin-auth-container">

    <div class="admin-left-banner-side">
        <div class="admin-banner-overlay-content">
            <h2 class="admin-banner-display-title">Management Portal</h2>
            <p class="admin-banner-display-subtitle">Register authorized administrator credentials to manage Crates N' Plates system operations.</p>
        </div>
    </div>

    <div class="admin-form-side">
        <div class="admin-auth-card">

            <div class="admin-view-title-group">
                <h2 class="admin-auth-title">Create Admin</h2>
                <p class="admin-auth-subtitle">Register a new system administrator account</p>
            </div>

            <form action="adminsignup_action.php" method="POST">

                <div class="admin-form-group">
                    <label class="form-label">Admin Name</label>
                    <input type="text" name="name" class="admin-form-input" placeholder="Enter your admin name" required>
                </div>

                <div class="admin-form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="admin-form-input" placeholder="Enter your admin email" required>
                </div>

                <div class="admin-form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="admin-form-input" placeholder="Enter your password" required>
                </div>

                <div class="admin-form-group">
                    <label class="form-label">Admin Key</label>
                    <input type="text" name="admin_key" class="admin-form-input" placeholder="Enter security access key" required>
                </div>

                <button type="submit" class="admin-action-submit-btn">Create Admin</button>

            </form>

            <div class="admin-auth-link">
                <p>Already have an account? <a href="admin_login.php">Login here</a></p>
            </div>

        </div>
    </div>

</div>

</body>
</html>