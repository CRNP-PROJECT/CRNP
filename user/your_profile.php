<?php
session_start();

if(!isset($_SESSION['user_id'])){
    header("Location: user_login.php");
    exit;
}

include("../config.php");
include("../firebaseRDB.php");

$rdb = new firebaseRDB($databaseURL);
$user_id = $_SESSION['user_id'];

// Get user data from Firebase
$data = $rdb->retrieve("/user/$user_id");
$user = json_decode($data, true);
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../styles.css">
<title>Your Profile</title>
</head>

<body class="your_profile">

<!-- ✅ FIXED NAVBAR -->
<nav class="navbar">
    <div class="navbar-brand-container">
        <img src="../img/logo.png" alt="Logo" class="logo">
        <a href="index.php" class="navbar-brand"></a>
    </div>

    <ul class="navbar-menu">
        <li><a href="index.php">Home</a></li>
        <li><a href="products.php">Products</a></li>
        <li><a href="booking.php">Booking</a></li>
        <li><a href="profile.php" class="active">Profile</a></li>
        <li><a href="../logout.php">Logout</a></li>
    </ul>
</nav>

<!-- ✅ WRAPPER -->
<div class="profile-container">
<div class="profile-card">

<h2>Edit Profile</h2>

<!-- ✅ STATUS -->
<?php
if(isset($_GET['status'])){
    if($_GET['status'] == "success"){
        echo "<p class='profile-message success'>Profile updated successfully!</p>";
    } elseif($_GET['status'] == "error"){
        echo "<p class='profile-message error'>Update failed. Please try again.</p>";
    }
}
?>

<form action="process.php" method="POST">

    <input type="hidden" name="action" value="update_profile">

    <input type="text" name="name"
        value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>

    <input type="email" name="email"
        value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>

    <input type="password" name="password"
        placeholder="New Password (leave blank if no change)">

    <button type="submit">Update Profile</button>

</form>

</div>
</div>

</body>
</html>