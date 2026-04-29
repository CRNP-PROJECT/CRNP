<?php
session_start();
include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['username'] ?? "User";
$rdb = new firebaseRDB($databaseURL);
$cart = $_SESSION['cart'] ?? [];
$total = 0;
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

<header class="navbar">
    <div class="navbar-brand-container">
        <img src="../img/logo.png" class="logo">
    </div>
    <div class="navbar-right">
        <ul class="navbar-menu">
            <li><a href="index.php">Home</a></li>
            <li><a href="products.php">Products</a></li>
            <li><a href="booking.php">Booking</a></li>
            <li><a href="cart.php" class="active">Cart</a></li>
            <li><a href="your_orders.php">Orders</a></li>
            <li><a href="aboutus.php">About</a></li>
        </ul>
        <div class="navbar-dropdown">
            <span class="navbar-user-btn">
                <i class="fa-regular fa-user"></i> <?php echo htmlspecialchars($username); ?> ▼
            </span>
            <div class="navbar-dropdown-content">
                <a href="your_profile.php">My Profile</a>
                <a href="your_orders.php">Your Orders</a>
                <a href="../logout.php">Logout</a>
            </div>
        </div>
    </div>
</header>

<div class="cart-wrapper">
    <h1 class="cart-title">YOUR CART (<?php echo array_sum($cart); ?> ITEMS)</h1>

    <?php if(empty($cart)): ?>
        <p class="cart-empty">Your cart is empty.</p>
    <?php else: ?>

    <div class="cart-table-header">
        <span>Product</span>
        <span>Price</span>
        <span>Quantity</span>
        <span>Action</span>
        <span>Subtotal</span>
    </div>

    <?php foreach($cart as $id => $qty): 
        $res = $rdb->retrieve("/products/$id");
        $product = json_decode($res, true);
        if (!$product) continue;
        $price = floatval($product['price']);
        $subtotal = $price * $qty;
        $total += $subtotal;
    ?>

    <div class="cart-row">
    <div class="cart-product">
        <img src="../admin/<?php echo htmlspecialchars($product['image']); ?>" class="cart-product-img">
        <span><?php echo htmlspecialchars($product['name']); ?></span>
    </div>

    <div class="cart-price">₱<?php echo number_format($price,2); ?></div>
    
    <div class="cart-qty">
        <form method="POST" action="process.php" id="form-<?php echo $id; ?>" class="cart-qty-form">
            <input type="hidden" name="action" value="update_cart">
            <input type="hidden" name="product_id" value="<?php echo $id; ?>">
            <button type="button" class="cart-qty-btn" onclick="this.form.quantity.stepDown()">-</button>
            <input type="number" name="quantity" value="<?php echo $qty; ?>" min="1" class="cart-qty-input">
            <button type="button" class="cart-qty-btn" onclick="this.form.quantity.stepUp()">+</button>
        </form>
    </div>

    <div class="cart-remove">
        <a href="process.php?action=remove_cart&id=<?php echo $id; ?>"><i class="fa-solid fa-trash"></i></a>
        <button type="button" class="cart-update-btn" onclick="document.getElementById('form-<?php echo $id; ?>').submit()">Update</button>
    </div>

    <div class="cart-subtotal">₱<?php echo number_format($subtotal,2); ?></div>
</div>
    <?php endforeach; ?>

    <div class="cart-summary">
        <div class="cart-summary-left">
            <p>Subtotal</p>
            <h3>TOTAL</h3>
        </div>
        <div class="cart-summary-right">
            <p>₱<?php echo number_format($total,2); ?></p>
            <h3>₱<?php echo number_format($total,2); ?></h3>
        </div>
        <div class="cart-summary-actions">
            <a href="checkout.php" class="cart-checkout-btn">Proceed to Checkout</a>
            <a href="products.php" class="cart-continue-link">Continue Shopping</a>
        </div>
    </div>
    <?php endif; ?>
</div>

</body>
</html>