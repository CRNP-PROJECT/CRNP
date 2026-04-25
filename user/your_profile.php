<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: user_login.php");
    exit;
}

include("../config.php");
include("../firebaseRDB.php");

$rdb = new firebaseRDB($databaseURL);
$user_id = $_SESSION['user_id'];

$data = $rdb->retrieve("/user/$user_id");
$user = json_decode($data, true) ?? [];

$username = $user['name'] ?? $_SESSION['username'] ?? "User";
$email = $user['email'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Your Profile</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../styles.css">
</head>

<body class="your_profile">

<!-- NAVBAR -->
<nav class="navbar">
    <div class="navbar-brand-container">
        <img src="../img/logo.png" alt="Logo" class="logo">
        <a href="index.php" class="navbar-brand"></a>
    </div>

    <ul class="navbar-menu">
        <li><a href="index.php"><i class="fa-solid fa-house"></i> Home</a></li>
        <li><a href="products.php"><i class="fa-solid fa-shop"></i> Products</a></li>
        <li><a href="booking.php"><i class="fa-solid fa-calendar-check"></i> Booking</a></li>
        <li><a href="cart.php"><i class="fa-solid fa-cart-shopping"></i> Cart</a></li>
        <li><a href="your_orders.php"><i class="fa-solid fa-box-open"></i> Orders</a></li>
        <li><a href="aboutus.php"><i class="fa-solid fa-circle-info"></i> About</a></li>

        <li class="navbar-dropdown">
            <a href="#">
                <i class="fa-solid fa-user"></i>
                <?= htmlspecialchars($username) ?> ▼
            </a>

            <div class="navbar-dropdown-content">
                <a href="your_profile.php" class="active">My Profile</a>
                <a href="your_orders.php">Your Orders</a>
                <a href="../logout.php">Logout</a>
            </div>
        </li>
    </ul>
</nav>

<!-- PROFILE -->
<div class="profile-wrapper">

    <div class="profile-card">

        <div class="profile-header">
            <div class="profile-avatar">
                <i class="fa-solid fa-user"></i>
            </div>

            <h2><?= htmlspecialchars($username) ?></h2>
            <p class="profile-sub">Manage your account information</p>
        </div>

        <!-- STATUS MESSAGE -->
        <?php if (isset($_GET['status'])): ?>
            <div class="profile-message <?= $_GET['status'] == 'success' ? 'success' : 'error' ?>">
                <?= $_GET['status'] == 'success' ? 'Profile updated successfully!' : 'Update failed. Try again.' ?>
            </div>
        <?php endif; ?>

        <!-- FORM -->
        <form action="process.php" method="POST">

            <input type="hidden" name="action" value="update_profile">

            <div class="input-group">
                <i class="fa-solid fa-user"></i>
                <input type="text" name="name"
                    value="<?= htmlspecialchars($user['name'] ?? '') ?>"
                    placeholder="Full Name" required>
            </div>

            <div class="input-group">
                <i class="fa-solid fa-envelope"></i>
                <input type="email" name="email"
                    value="<?= htmlspecialchars($email) ?>"
                    placeholder="Email Address" required>
            </div>

            <div class="input-group">
                <i class="fa-solid fa-lock"></i>
                <input type="password" name="password"
                    placeholder="New Password (optional)">
            </div>

            <button type="submit">
                <i class="fa-solid fa-pen-to-square"></i> Update Profile
            </button>

        </form>

    </div>

</div>

</body>
</html>