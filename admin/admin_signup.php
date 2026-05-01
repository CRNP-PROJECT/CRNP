<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Signup</title>

<!-- Google Font -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">

<!-- Icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<!-- CSS -->
<link rel="stylesheet" href="../style.css">
</head>

<body class="admin-auth-body">

<div class="admin-auth-container">

    <!-- LEFT SIDE -->
    <div class="admin-form-side">
        <div class="admin-auth-card">

            <h2 class="admin-auth-title">Create Admin</h2>
            <p class="admin-auth-subtitle">Register a new admin account</p>

            <form action="adminsignup_action.php" method="POST">

                <div class="admin-form-group">
                    
                    <input type="text" name="name" class="admin-form-input" placeholder="Admin Name" required>
                </div>

                <div class="admin-form-group">
                    
                    <input type="email" name="email" class="admin-form-input" placeholder="Admin Email" required>
                </div>

                <div class="admin-form-group">
                    
                    <input type="password" name="password" class="admin-form-input" placeholder="Password" required>
                </div>

                <div class="admin-form-group">
                    
                    <input type="text" name="admin_key" class="admin-form-input" placeholder="Admin Key" required>
                </div>

                <button type="submit">Create Admin</button>

            </form>

            <div class="admin-auth-link">
                <p>Already have an account? <a href="admin_login.php">Login</a></p>
            </div>

        </div>
    </div>

    <!-- RIGHT SIDE -->
    <div class="admin-visual-side">
        <div class="admin-profile-circle">
            <img src="https://cdn-icons-png.flaticon.com/512/4140/4140048.png">
        </div>
    </div>

</div>

</body>
</html>