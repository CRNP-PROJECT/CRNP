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
    <title>User Dashboard | Crates N' Plates</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../styles.css">
</head>
<body class="dashboard-body">

<div class="bg-slideshow">
    <div class="bg-slide s1"></div>
    <div class="bg-slide s2"></div>
    <div class="bg-slide s3"></div>
    <div class="bg-slide s4"></div>
</div>

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
        <li><a href="aboutus.php"><i class="fa-solid fa-circle-info"></i> About Us</a></li>
        
        <li class="navbar-dropdown">
            <a href="#"><i class="fa-solid fa-user"></i> <?php echo htmlspecialchars($username); ?> ▼</a>
            <div class="navbar-dropdown-content">
                <a href="your_profile.php"><i class="fa-solid fa-id-card"></i> My Profile</a>
                <a href="your_orders.php"><i class="fa-solid fa-box"></i> Your Orders</a>
                <a href="../logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </div>
        </li>
    </ul>
</nav>

<div class="container">
    <div class="card">
        <div class="card-icon">
            <i class=" "></i>
        </div>

        <h1 class="card-title">Welcome, <?php echo htmlspecialchars($username); ?>!</h1>
        
        <p class="email-text">
            <i class="fa-solid fa-envelope"></i> 
            Email: <span><?php echo htmlspecialchars($email); ?></span>
        </p>
    </div>
</div>

</body>
</html>