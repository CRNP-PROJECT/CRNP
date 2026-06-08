<?php
session_start();

include(__DIR__ . "/../config.php");

if(!isset($_SESSION['email'])){
    header("Location: login.php");
    exit;
}

$username = $_SESSION['username'] ?? "User";
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../styles.css">

<title>Booking Success</title>
</head>

<body class="booking-success-page-body">

<!-- ✅ NAVBAR (MATCHED TO BOOKING PAGE) -->
<header class="navbar">
    <div class="navbar-brand-container">
        <img src="../img/logo.png" class="logo">
    </div>

    <div class="navbar-right">
        <ul class="navbar-menu">
            <li><a href="index.php">Home</a></li>
            <li><a href="products.php">Products</a></li>
            <li><a href="booking.php">Booking</a></li>
            <li><a href="cart.php">Cart</a></li>
            <li><a href="your_orders.php">Orders</a></li>
            <li><a href="aboutus.php">About</a></li>
        </ul>

        <div class="navbar-dropdown">
            <span class="navbar-user-btn">
                <?php echo htmlspecialchars($username); ?> ▼
            </span>

            <div class="navbar-dropdown-content">
                <a href="your_profile.php">My Profile</a>
                <a href="your_orders.php">Your Orders</a>
                <a href="../logout.php">Logout</a>
            </div>
        </div>
    </div>
</header>

<!-- MAIN -->
<div class="booking-success-container">
    <div class="booking-success-card">

        <h1 class="booking-success-title">Booking Confirmed!</h1>

        <p class="booking-success-text">
            Your reservation has been successfully recorded.
        </p>

        <p class="booking-success-text">
            We’ll contact you shortly for confirmation.
        </p>

        <a href="index.php" class="booking-success-btn">
            <span class="btn-arrow">&larr;</span>Back to Home
        </a>

    </div>
</div>

</body>
</html>