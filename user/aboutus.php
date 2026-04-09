<?php
include(__DIR__ . "/../config.php");

if(!isset($_SESSION['email'])){
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../styles.css">
<title>About Us</title>
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="navbar-brand">CRNP</a>
    <ul class="navbar-menu">
        <li><a href="index.php">Home</a></li>
        <li><a href="products.php">Products</a></li>
        <li><a href="booking.php">Booking</a></li>
        <li><a href="../logout.php">Logout</a></li>
    </ul>
</nav>

<div class="container">
    <div class="card">
        <h1 class="page-title">About Us</h1>
        <p class="text-muted mt-2">Welcome to CRNP - Your trusted partner for events and celebrations.</p>
        
        <div class="kpi-grid mt-3">
            <div class="kpi-card">
                <div class="kpi-value">10+</div>
                <div class="kpi-label">Years Experience</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-value">500+</div>
                <div class="kpi-label">Events Served</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-value">100%</div>
                <div class="kpi-label">Customer Satisfaction</div>
            </div>
        </div>

        <h2 class="card-title mt-3">Our Services</h2>
        <p>We provide quality rental equipment for your special occasions including tables, chairs, and skirting cloths. Our commitment is to make your events memorable and hassle-free.</p>

        <h2 class="card-title mt-3">Contact Us</h2>
        <p>For inquiries and reservations, please don't hesitate to reach out. We're here to help you plan the perfect event.</p>
    </div>
</div>

</body>
</html>
