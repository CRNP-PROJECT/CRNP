<?php
session_start();

include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}

$rdb = new firebaseRDB($databaseURL);
$cart = $_SESSION['cart'] ?? [];
$total = 0;

/* 🔥 AUTO ACTIVE NAV */
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../styles.css">

<title>Your Cart</title>
</head>

<body class="cart-page-body">

<!-- NAVBAR -->
<nav class="navbar">
    <div class="navbar-brand-container">
        <img src="../img/logo.png" alt="Logo" class="logo">
        <a href="index.php" class="navbar-brand"></a>
    </div>

    <ul class="navbar-menu">

        <li>
            <a href="index.php" class="<?= $current_page == 'index.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-house-chimney"></i> Home
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
            <a href="aboutus.php">
                <i class="fa-solid fa-circle-info"></i> About Us
            </a>
        </li>

        <li>
            <a href="../logout.php">
                <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
            </a>
        </li>

    </ul>
</nav>

<!-- CONTENT -->
<div class="container">

    <div class="page-header">
        <h1 class="page-title">
            <i class="fa-solid fa-cart-shopping"></i> Your Cart
        </h1>
    </div>

    <a href="products.php" class="btn-secondary">
        <i class="fa-solid fa-chevron-left"></i> Continue Shopping
    </a>

    <?php if(empty($cart)): ?>

        <div class="card">
            <p class="text-center text-muted">Your cart is empty.</p>
        </div>

    <?php else: ?>

        <?php foreach($cart as $id => $qty): 

            $res = $rdb->retrieve("/products/$id");
            $product = json_decode($res, true);

            if (!$product) continue;

            $price = floatval($product['price'] ?? 0);
            $subtotal = $price * $qty;
            $total += $subtotal;
        ?>

        <div class="cart-item">

            <img src="../admin/<?php echo htmlspecialchars($product['image'] ?? 'default.png'); ?>">

            <div class="cart-item-info">
                <h3><?php echo htmlspecialchars($product['name'] ?? 'Item'); ?></h3>

                <p><i class="fa-solid fa-peso-sign"></i> Price: ₱<?php echo number_format($price, 2); ?></p>
                <p><i class="fa-solid fa-receipt"></i> Subtotal: ₱<?php echo number_format($subtotal, 2); ?></p>
            </div>

            <div class="cart-item-actions">

                <form method="POST" action="process.php">
                    <input type="hidden" name="action" value="update_cart">
                    <input type="hidden" name="product_id" value="<?php echo $id; ?>">

                    <div class="flex gap-1">
                        <input type="number" name="quantity" value="<?php echo $qty; ?>" min="1" class="form-input" style="width: 70px;">

                        <button type="submit" class="btn-update">
                            <i class="fa-solid fa-arrows-rotate"></i> Update
                        </button>
                    </div>
                </form>

                <a href="process.php?action=remove_cart&id=<?php echo $id; ?>" 
                   class="btn-danger" 
                   onclick="return confirm('Remove item?')">
                    <i class="fa-solid fa-trash"></i> Remove
                </a>

            </div>
        </div>

        <?php endforeach; ?>

        <div class="card mt-2">
            <p class="cart-total">
                Total: ₱<?php echo number_format($total, 2); ?>
            </p>

            <a href="checkout.php" class="btn-primary">
                <i class="fa-solid fa-wallet"></i> Proceed to Checkout
            </a>
        </div>

    <?php endif; ?>

</div>

</body>
</html>