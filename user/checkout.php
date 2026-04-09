<?php

include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

if(!isset($_SESSION['email'])){
    header("Location: login.php");
    exit;
}

$rdb = new firebaseRDB($databaseURL);

$cart = $_SESSION['cart'] ?? [];

if(empty($cart)){
    header("Location: cart.php");
    exit;
}

$cart_items = [];
$total = 0;
foreach($cart as $id => $qty){
    $product = json_decode($rdb->retrieve("/products/$id"), true);
    if(!$product) continue;

    $product['qty'] = $qty;
    $product['subtotal'] = floatval($product['price']) * $qty;
    $total += $product['subtotal'];

    $cart_items[$id] = $product;
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../styles.css">
<title>Checkout</title>
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="navbar-brand">CRNP</a>
    <ul class="navbar-menu">
        <li><a href="products.php">Products</a></li>
        <li><a href="cart.php">Cart</a></li>
    </ul>
</nav>

<div class="container">
    <div class="page-header">
        <h1 class="page-title">Checkout & Reservation</h1>
        <a href="cart.php" class="btn btn-secondary btn-sm">← Back to Cart</a>
    </div>

    <div class="card mb-3">
        <h3 class="card-title">Order Summary</h3>
        <?php foreach($cart_items as $id => $item): ?>
        <div style="padding: 12px 0; border-bottom: 1px solid var(--border);">
            <strong><?php echo htmlspecialchars($item['name']); ?></strong>
            <span class="text-muted"> × <?php echo $item['qty']; ?></span>
            <span class="text-right" style="float: right;">₱<?php echo number_format($item['subtotal'], 2); ?></span>
        </div>
        <?php endforeach; ?>
        <p class="cart-total">Total: ₱<?php echo number_format($total, 2); ?></p>
    </div>

    <div class="card">
        <h3 class="card-title">Reservation Information</h3>
        <form action="process.php" method="POST">
            <input type="hidden" name="action" value="confirm_checkout">

            <div class="form-group">
                <label class="form-label">Full Name</label>
                <input type="text" name="full_name" class="form-input" required>
            </div>

            <div class="form-group">
                <label class="form-label">Contact Number</label>
                <input type="text" name="contact_number" class="form-input" required>
            </div>

            <div class="form-group">
                <label class="form-label">Number of People</label>
                <input type="number" name="num_people" class="form-input" min="1" required>
            </div>

            <div class="form-group">
                <label class="form-label">Reservation Date & Time</label>
                <input type="datetime-local" name="appointment_time" class="form-input" required>
            </div>

            <button type="submit" class="btn btn-primary btn-lg btn-block">Confirm & Checkout</button>
        </form>
    </div>
</div>

</body>
</html>
