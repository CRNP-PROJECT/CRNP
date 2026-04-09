<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Signup</title>
    
</head>
<body>

<div class="container">
    <h2>Admin Signup</h2>

    <form action="adminsignup_action.php" method="POST">
        <input type="text" name="name" placeholder="Admin Name" required><br>
        <input type="email" name="email" placeholder="Admin Email" required><br>
        <input type="password" name="password" placeholder="Password" required><br>
        
        <!-- 🔐 Admin Secret Key -->
        <input type="text" name="admin_key" placeholder="Admin Key" required><br>

        <button type="submit">Create Admin</button><br>
    </form>

    <div class="link">
        <p>Already have an account? <a href="admin_login.php">Login here</a></p>
    </div>
</div>

</body>
</html>