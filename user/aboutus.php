<?php
session_start();

include(__DIR__ . "/../config.php");

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['username'] ?? "User";
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
</head>

<body class="about-page-body">

<!-- NAVBAR -->
<header class="navbar">
    <div class="navbar-brand-container">
        <img src="../img/logo.png" class="logo">
    </div>

    <div class="navbar-right">
        <ul class="navbar-menu">
            <li><a href="index.php" class="<?= $current_page == 'index.php' ? 'active' : '' ?>">Home</a></li>
            <li><a href="products.php" class="<?= $current_page == 'products.php' ? 'active' : '' ?>">Products</a></li>
            <li><a href="booking.php" class="<?= $current_page == 'booking.php' ? 'active' : '' ?>">Booking</a></li>
            <li><a href="cart.php" class="<?= $current_page == 'cart.php' ? 'active' : '' ?>">Cart</a></li>
            <li><a href="your_orders.php" class="<?= $current_page == 'your_orders.php' ? 'active' : '' ?>">Orders</a></li>
            <li><a href="aboutus.php" class="<?= $current_page == 'aboutus.php' ? 'active' : '' ?>">About Us</a></li>
        </ul>

        <div class="navbar-dropdown">
            <span class="navbar-user-btn">
                <i class="fa-regular fa-user"></i>
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

<!-- CONTENT -->
<div class="about-container">

    <div class="about-card">

        <!-- LEFT SIDE -->
        <div class="about-left">

            <h1 class="about-header">ABOUT US</h1>

            <p class="about-subtitle">
                Your trusted partner for events and celebrations.
            </p>

            <!-- KPI -->
            <div class="about-kpi-grid">

                <div class="about-kpi-card">
                    <div class="about-kpi-value">10+</div>
                    <div class="about-kpi-label">Years Experience</div>
                </div>

                <div class="about-kpi-card">
                    <div class="about-kpi-value">500+</div>
                    <div class="about-kpi-label">Events Served</div>
                </div>

                <div class="about-kpi-card">
                    <div class="about-kpi-value">100%</div>
                    <div class="about-kpi-label">Satisfaction</div>
                </div>

            </div>

            <!-- SECTION 1 -->
            <div class="about-section">
                <h2 class="about-section-title">Curated Essentials</h2>
                <p>
                    We provide quality rental equipment for your special occasions including tables,
                    chairs, and skirting cloths. Our commitment is to make your events memorable and hassle-free.
                </p>
            </div>

            <!-- SECTION 2 -->
            <div class="about-section">
                <h2 class="about-section-title">Let's Connect</h2>
                <p>
                    For inquiries and reservations, feel free to contact us anytime.
                </p>
            </div>

            <!-- SECTION 3 -->
            <div class="about-section">
                <h2 class="about-section-title">Visit Our Location</h2>
                <p>
                    Mabolo, Iloilo City Proper, Iloilo City, Philippines.
                </p>
            </div>

        </div>

        <!-- RIGHT SIDE -->
        <div class="about-right">

            <div class="about-map-wrapper">
                <iframe 
                    src="https://www.google.com/maps?q=Crates%20N'%20Plates%20Diner%20Iloilo&output=embed"
                    allowfullscreen=""
                    loading="lazy">
                </iframe>
            </div>

        </div>

    </div>

</div>

</body>
</html>