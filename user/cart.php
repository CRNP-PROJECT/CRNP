<?php

include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}

$rdb = new firebaseRDB($databaseURL);
$cart = $_SESSION['cart'] ?? [];
$total = 0;
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../styles.css">
<title>Your Cart</title>
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="navbar-brand">CRNP</a>
    <ul class="navbar-menu">
        <li><a href="index.php">Home</a></li>
        <li><a href="products.php">Products</a></li>
        <li><a href="cart.php">Cart</a></li>
        <li><a href="../logout.php">Logout</a></li>
    </ul>
</nav>

<div class="container">
    <div class="page-header flex-between">
        <div>
            <h1 class="page-title">Your Cart</h1>
            <a href="products.php" class="btn btn-secondary btn-sm">← Continue Shopping</a>
        </div>
    </div>

    <?php if(empty($cart)): ?>
        <div class="card">
            <p class="text-center text-muted">Your cart is empty.</p>
        </div>
    <?php else: ?>

        <?php foreach($cart as $id => $qty): 
            $product = json_decode($rdb->retrieve("/products/$id"), true);
            if (!$product) continue;

            $price = floatval($product['price']);
            $subtotal = $price * $qty;
            $total += $subtotal;
        ?>

        <div class="cart-item">
            <img src="../admin/<?php echo htmlspecialchars($product['image']); ?>">
            <div class="cart-item-details">
                <h3 class="product-card-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                <p class="text-muted">Price: ₱<?php echo number_format($price, 2); ?></p>
                <p class="text-muted">Subtotal: ₱<?php echo number_format($subtotal, 2); ?></p>
            </div>
            <div class="cart-item-actions">
                <form method="POST" action="process.php">
                    <input type="hidden" name="action" value="update_cart">
                    <input type="hidden" name="product_id" value="<?php echo $id; ?>">
                    <div class="flex gap-1">
                        <input type="number" name="quantity" value="<?php echo $qty; ?>" min="1" class="form-input" style="width: 70px;">
                        <button type="submit" class="btn btn-secondary btn-sm">Update</button>
                    </div>
                </form>
                <a href="process.php?action=remove_cart&id=<?php echo $id; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Remove item?')">Remove</a>
            </div>
        </div>

        <?php endforeach; ?>

        <div class="card mt-2">
            <p class="cart-total">Total: ₱<?php echo number_format($total, 2); ?></p>
            <a href="checkout.php" class="btn btn-primary btn-lg btn-block">Proceed to Checkout</a>
        </div>

    <?php endif; ?>
</div>

</body>
</html>
