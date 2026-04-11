<?php
include("../config.php");

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit;
}

$email = $_SESSION['email'];
$username = $_SESSION['username'] ?? "User";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../styles.css">
    <title>User Dashboard</title>
</head>
<body class="dashboard-body">

<nav class="navbar">
    <div class="navbar-brand-container">
        <img src="../img/logo.png" alt="Logo" class="logo" 
             style="height: 160px !important; width: auto !important; display: block; object-fit: contain;">
        <a href="index.php" class="navbar-brand"></a>
    </div>

    <ul class="navbar-menu">
        <li><a href="index.php">Home</a></li>
        <li><a href="products.php">Products</a></li>
        <li><a href="booking.php">Booking</a></li>
        <li><a href="aboutus.php">About Us</a></li>
        <li><a href="cart.php">Cart</a></li>
        <li class="navbar-dropdown">
            <a href="#"><?php echo htmlspecialchars($username); ?> ▼</a>
            <div class="navbar-dropdown-content">
                <a href="your_profile.php">My Profile</a>
                <a href="your_orders.php">Your Orders</a>
                <a href="../logout.php">Logout</a>
            </div>
        </li>
    </ul>
</nav>

<div class="container">
    <div class="card">
        <h1 class="card-title">Welcome, <?php echo htmlspecialchars($username); ?>!</h1>
        <p class="text-muted">Email: <?php echo htmlspecialchars($email); ?></p>
    </div>
</div>

</body>
</html>