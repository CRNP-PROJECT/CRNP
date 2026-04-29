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
    <title>Order Confirmation</title>
</head>
<body class="checkout-success-page">

<nav class="navbar">
    <div class="navbar-brand-container">
        <img src="../img/logo.png" alt="Logo" class="logo">
    </div>

    <ul class="navbar-menu">
        <li><a href="index.php">Home</a></li>
        <li><a href="products.php">Products</a></li>
        <li><a href="booking.php">Booking</a></li>
        <li><a href="cart.php">Cart</a></li>
        <li><a href="../logout.php">Logout</a></li>
    </ul>
</nav>

<div class="checkout-success-wrapper">

    <div class="checkout-success-card">

        <div class="checkout-success-icon">
            ✓
        </div>

        <h1 class="checkout-success-title">
            Order Confirmed
        </h1>

        <p class="checkout-success-text">
            Your reservation/order has been successfully placed.
        </p>

        <?php if($user_email): ?>
            <p class="checkout-success-email">
                Confirmation sent to:<br>
                <strong><?php echo htmlspecialchars($user_email); ?></strong>
            </p>
        <?php endif; ?>

        <div class="checkout-success-actions">
            <a href="products.php" class="checkout-success-btn">
                Continue Shopping
            </a>
        </div>

    </div>

</div>

</body>
</html>