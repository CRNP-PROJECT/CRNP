<?php
include(__DIR__ . "/../config.php");

$user_email = $_SESSION['email'] ?? '';
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../styles.css">
<title>Order Confirmation</title>
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="navbar-brand">CRNP</a>
    <ul class="navbar-menu">
        <li><a href="products.php">Products</a></li>
        <li><a href="cart.php">Cart</a></li>
    </ul>
</nav>

<div class="container" style="padding-top: 80px;">
    <div class="container-sm">
        <div class="card text-center">
            <div style="font-size: 48px; margin-bottom: 16px;">✓</div>
            <h1 class="auth-title" style="color: var(--success);">Thank you for your order!</h1>
            <p class="text-muted mt-2">Your reservation/order has been successfully placed.</p>
            <?php if($user_email): ?>
                <p class="text-muted">Confirmation sent to: <strong><?php echo htmlspecialchars($user_email); ?></strong></p>
            <?php endif; ?>
            <a href="products.php" class="btn btn-primary mt-3">Continue Shopping</a>
        </div>
    </div>
</div>

</body>
</html>
