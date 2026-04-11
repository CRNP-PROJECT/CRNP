<?php
session_start();

include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}

$rdb = new firebaseRDB($databaseURL);

// 🔥 CATEGORY FILTER
$filter = $_GET['category'] ?? "All";

$products = [];

try {
    $retrieve = $rdb->retrieve("/products");
    $data = json_decode($retrieve, true);

    if (is_array($data)) {

        foreach ($data as $id => $product) {

            $category = $product['category'] ?? 'Food';

            if ($filter === "All" || $category === $filter) {
                $products[$id] = $product;
            }
        }
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

<style>
.filter-btn {
    margin-right: 5px;
    padding: 6px 12px;
    border: 1px solid #ccc;
    border-radius: 5px;
    text-decoration: none;
}

.filter-active {
    background: #007bff;
    color: white;
}
</style>

</head>
<body class="products-page-body">

<nav class="navbar">
    <div class="navbar-brand-container">
        <img src="../img/logo.png" alt="Logo" class="logo" 
             style="height: 160px !important; width: auto !important; display: block; object-fit: contain;">
        <a href="index.php" class="navbar-brand"></a>
    </div>
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

        <!-- 🔥 CATEGORY FILTER -->
        <div style="margin-top:10px;">
            <a href="?category=All" class="filter-btn <?php echo ($filter=='All')?'filter-active':''; ?>">All</a>
            <a href="?category=Food" class="filter-btn <?php echo ($filter=='Food')?'filter-active':''; ?>">Food</a>
            <a href="?category=Drinks" class="filter-btn <?php echo ($filter=='Drinks')?'filter-active':''; ?>">Drinks</a>
            <a href="?category=Beverages" class="filter-btn <?php echo ($filter=='Beverages')?'filter-active':''; ?>">Beverages</a>
        </div>

    </div>

    <?php if(empty($products)): ?>
        <div class="card">
            <p class="text-center text-muted">No products available.</p>
        </div>
    <?php else: ?>

        <div class="product-grid">

            <?php foreach($products as $id => $product): ?>
            <div class="product-card">

                <!-- 🔥 FIX IMAGE PATH -->
                <img src="../admin/<?php echo htmlspecialchars($product['image']); ?>"
                     alt="<?php echo htmlspecialchars($product['name']); ?>">

                <div class="product-card-body">

                    <h3 class="product-card-title">
                        <?php echo htmlspecialchars($product['name']); ?>
                    </h3>

                    <!-- 🔥 CATEGORY DISPLAY -->
                    <small style="color:gray;">
                        <?php echo htmlspecialchars($product['category'] ?? 'Food'); ?>
                    </small>

                    <p class="product-card-price">
                        ₱<?php echo number_format($product['price'], 2); ?>
                    </p>

                    <p class="product-card-desc">
                        <?php echo htmlspecialchars($product['description']); ?>
                    </p>

                    <div class="product-card-actions">

                        <form action="process.php" method="POST" style="flex:1;">
                            <input type="hidden" name="action" value="add_to_cart">
                            <input type="hidden" name="product_id" value="<?php echo $id; ?>">
                            <button type="submit" class="btn btn-secondary btn-sm btn-block">
                                Add to Cart
                            </button>
                        </form>

                        <form action="process.php" method="POST" style="flex:1;">
                            <input type="hidden" name="action" value="buy_now">
                            <input type="hidden" name="product_id" value="<?php echo $id; ?>">
                            <button type="submit" class="btn btn-primary btn-sm btn-block">
                                Buy Now
                            </button>
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