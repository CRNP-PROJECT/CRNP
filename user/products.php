<?php

include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}

$products = [];

try {
    $rdb = new firebaseRDB($databaseURL);
    $retrieve = $rdb->retrieve("/products");
    $data = json_decode($retrieve, true);

    if (is_array($data)) {
        $products = $data;
    }

} catch(Exception $e) {
    echo "Error: " . $e->getMessage();
}

$cartCount = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../styles.css">
<title>Products</title>
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="navbar-brand">CRNP</a>
    <ul class="navbar-menu">
        <li><a href="index.php">Home</a></li>
        <li><a href="products.php">Products</a></li>
        <li><a href="cart.php">Cart (<?php echo $cartCount; ?>)</a></li>
        <li><a href="your_orders.php">Your Orders</a></li>
        <li><a href="../logout.php">Logout</a></li>
    </ul>
</nav>

<div class="container">
    <div class="page-header">
        <h1 class="page-title">Available Products</h1>
        <p class="page-subtitle">Browse our selection of products</p>
    </div>

    <?php if(empty($products)): ?>
        <div class="card">
            <p class="text-center text-muted">No products available.</p>
        </div>
    <?php else: ?>
        <div class="product-grid">
            <?php foreach($products as $id => $product): ?>
            <div class="product-card">
                <img src="../admin/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                <div class="product-card-body">
                    <h3 class="product-card-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                    <p class="product-card-price">₱<?php echo number_format($product['price'], 2); ?></p>
                    <p class="product-card-desc"><?php echo htmlspecialchars($product['description']); ?></p>
                    <div class="product-card-actions">
                        <form action="process.php" method="POST" style="flex:1;">
                            <input type="hidden" name="action" value="add_to_cart">
                            <input type="hidden" name="product_id" value="<?php echo $id; ?>">
                            <button type="submit" class="btn btn-secondary btn-sm btn-block">Add to Cart</button>
                        </form>
                        <form action="process.php" method="POST" style="flex:1;">
                            <input type="hidden" name="action" value="buy_now">
                            <input type="hidden" name="product_id" value="<?php echo $id; ?>">
                            <button type="submit" class="btn btn-primary btn-sm btn-block">Buy Now</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
