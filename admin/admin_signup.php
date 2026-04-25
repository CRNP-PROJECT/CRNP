<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Signup</title>
<link rel="stylesheet" href="css/style.css">
</head>

<body class="admin-auth-body">

<div class="admin-auth-container">

    <div class="admin-auth-card">

        <h2 class="admin-auth-title">Admin Signup</h2>
        <p class="admin-auth-subtitle">Create an admin account</p>

        <form action="adminsignup_action.php" method="POST">

            <input type="text" name="name" placeholder="Admin Name" required>

            <input type="email" name="email" placeholder="Admin Email" required>

            <input type="password" name="password" placeholder="Password" required>

            <input type="text" name="admin_key" placeholder="Admin Key" required>

            <button type="submit">Create Admin</button>

        </form>

        <div class="admin-auth-link">
            <p>Already have an account? <a href="admin_login.php">Login here</a></p>
        </div>

    </div>

</div>

</body>
</html>