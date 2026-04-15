<?php
include(__DIR__ . "/../config.php");
// Ensure session is started to access email
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$user_email = $_SESSION['email'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="checkout_styles.css"> 
    <title>Order Confirmation | CRNP</title>
</head>
<body class="success-page">

<nav class="navbar">
    <div class="navbar-brand-container">
        <img src="../img/logo.png" alt="Logo" class="logo"> 
    </div>
    <ul class="navbar-menu">
        <li><a href="index.php"><i class="fa-solid fa-house"></i> Home</a></li>
        <li><a href="products.php"><i class="fa-solid fa-shop"></i> Products</a></li>
        <li><a href="bookings.php"><i class="fa-solid fa-calendar-check"></i> Booking</a></li>
        <li><a href="about.php"><i class="fa-solid fa-circle-info"></i> About Us</a></li>
        <li><a href="cart.php"><i class="fa-solid fa-cart-shopping"></i> Cart</a></li>
        <li><a href="../logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
    </ul>
</nav>

<div class="container" style="padding-top: 80px;">
    <div class="container-sm">
        <div class="card text-center">
            <div style="font-size: 48px; margin-bottom: 16px; color: #f3e5ab;">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            
            <h1 class="auth-title" style="color: #f3e5ab;">Thank you for your order!</h1>
            <p class="text-muted mt-2">Your reservation/order has been successfully placed.</p>
            
            <?php if($user_email): ?>
                <p class="text-muted">
                    <i class="fa-solid fa-envelope"></i> 
                    Confirmation sent to: <strong><?php echo htmlspecialchars($user_email); ?></strong>
                </p>
            <?php endif; ?>

            <a href="products.php" class="btn btn-primary mt-3">
                <i class="fa-solid fa-chevron-left"></i> Continue Shopping
            </a>
        </div>
    </div>
</div>

</body>
</html>