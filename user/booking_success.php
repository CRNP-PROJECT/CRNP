<?php
include(__DIR__ . "/../config.php");
if(!isset($_SESSION['email'])){ header("Location: login.php"); exit; }
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../styles.css">
<title>Booking Success</title>
</head>

<body class="success-page-body">

<nav class="navbar">
    <div class="navbar-brand-container">
        <img src="../img/logo.png" class="logo">
        <a href="index.php" class="navbar-brand"> </a>
    </div>
    <ul class="navbar-menu">
        <li><a href="index.php"><i class="fa-solid fa-house"></i> Home</a></li>
        <li><a href="products.php"><i class="fa-solid fa-shop"></i> Products</a></li>
        <li><a href="booking.php"><i class="fa-solid fa-calendar-check"></i> Booking</a></li>
        <li><a href="cart.php"><i class="fa-solid fa-cart-shopping"></i> Cart</a></li>
        <li><a href="aboutus.php"><i class="fa-solid fa-circle-info"></i> About</a></li>
        <li><a href="../logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
    </ul>
</nav>

<div class="container">
    <div class="container-sm">
        <div class="card success-card">
            <div class="success-icon-wrapper">
                <i class="fa-solid fa-circle-check"></i>
            </div>

            <h1 class="auth-title">Booking Confirmed!</h1>
            <p class="text-muted">Your reservation has been successfully recorded.</p>
            <p class="text-muted">We'll contact you if needed.</p>

            <a href="index.php" class="btn btn-primary-back">
                <i class="fa-solid fa-arrow-left"></i> Back to Home
            </a>
        </div>
    </div>
</div>

</body>
</html>