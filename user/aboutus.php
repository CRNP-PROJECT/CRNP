<?php
session_start();

include(__DIR__ . "/../config.php");

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['username'] ?? "User";

/* 🔥 AUTO ACTIVE NAV */
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>About Us | CRNP</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&family=Playfair+Display:ital,wght@0,700;1,700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="about_styles.css">
</head>

<body class="about-page-body">

<!-- NAVBAR -->
<nav class="navbar">
    <div class="navbar-brand-container">
        <img src="../img/logo.png" alt="Logo" class="logo">
    </div>

    <ul class="navbar-menu">

        <li>
            <a href="index.php" class="<?= $current_page == 'index.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-house"></i> Home
            </a>
        </li>

        <li>
            <a href="products.php" class="<?= $current_page == 'products.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-shop"></i> Products
            </a>
        </li>

        <li>
            <a href="booking.php" class="<?= $current_page == 'booking.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-calendar-check"></i> Booking
            </a>
        </li>

        <li>
            <a href="cart.php" class="<?= $current_page == 'cart.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-cart-shopping"></i> Cart
            </a>
        </li>

        <li>
            <a href="aboutus.php" class="<?= $current_page == 'aboutus.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-circle-info"></i> About Us
            </a>
        </li>

        <li>
            <a href="../logout.php">
                <i class="fa-solid fa-right-from-bracket"></i> Logout
            </a>
        </li>

    </ul>
</nav>

<!-- CONTENT -->
<div class="about-container">

    <div class="about-card main-reveal">

        <h1 class="about-header">
            <span class="glitch-text">About Us</span>
            <i class="fa-solid fa-dna floating-icon"></i>
        </h1>

        <p class="about-subtitle">
            Your trusted partner for events and celebrations.
        </p>

        <!-- KPI -->
        <div class="kpi-grid">

            <div class="kpi-card" style="--delay: 1;">
                <div class="kpi-icon"><i class="fa-solid fa-hourglass-start"></i></div>
                <div class="kpi-value">10+</div>
                <div class="kpi-label">Years Experience</div>
            </div>

            <div class="kpi-card" style="--delay: 2;">
                <div class="kpi-icon"><i class="fa-solid fa-clover"></i></div>
                <div class="kpi-value">500+</div>
                <div class="kpi-label">Events Served</div>
            </div>

            <div class="kpi-card" style="--delay: 3;">
                <div class="kpi-icon"><i class="fa-solid fa-heart-pulse"></i></div>
                <div class="kpi-value">100%</div>
                <div class="kpi-label">Satisfaction</div>
            </div>

        </div>

        <!-- SECTION 1 -->
        <div class="about-content-section">
            <h2 class="section-title">
                <i class="fa-solid fa-star"></i> Curated Essentials
            </h2>

            <p>
                We provide quality rental equipment for your special occasions including tables,
                chairs, and skirting cloths. Our commitment is to make your events memorable and hassle-free.
            </p>
        </div>

        <!-- SECTION 2 -->
        <div class="about-content-section">
            <h2 class="section-title">
                <i class="fa-solid fa-envelope-open-text"></i> Let's Connect
            </h2>

            <p>
                For inquiries and reservations, please don't hesitate to reach out. We're here to help
                you plan the perfect event.
            </p>
        </div>

        <!-- SECTION 3 -->
        <div class="about-content-section">
            <h2 class="section-title">
                <i class="fa-solid fa-map-location-dot"></i> Visit Our Location
            </h2>

            <p>
                Mabolo, Iloilo City Proper, Iloilo City, Philippines.
            </p>

            <div class="map-wrapper">
                <iframe 
                    src="https://www.google.com/maps?q=Crates%20N'%20Plates%20Diner%20Iloilo&output=embed"
                    width="100%" 
                    height="350" 
                    style="border:0;" 
                    allowfullscreen="" 
                    loading="lazy">
                </iframe>
            </div>
        </div>

    </div>

</div>

</body>
</html>