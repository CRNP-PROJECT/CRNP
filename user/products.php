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
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../styles.css">
<title>Products</title>

<style>
.filter-btn {
    margin-right: 5px;
    padding: 8px 15px; /* Slightly adjusted for icons */
    border: 1px solid #ccc;
    border-radius: 5px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.filter-active {
    background: #80461B; /* Tawny brand color */
    color: white;
    border-color: #80461B;
}

/* 2. ADDED: Helper for button icons spacing */
.btn i {
    margin-right: 5px;
}
</style>

</head>
<body class="products-page-body">

<nav class="navbar">
    <div class="navbar-brand-container">
        <img src="../img/logo.png" alt="Logo" class="logo">
        <a href="products.php" class="navbar-brand"></a>
    </div>
    <ul class="navbar-menu">
        <li><a href="index.php"><i class="fa-solid fa-house"></i> Home</a></li>
        <li><a href="products.php"><i class="fa-solid fa-shop"></i> Products</a></li>
        <li><a href="booking.php"><i class="fa-solid fa-calendar-check"></i> Booking</a></li>
        <li><a href="cart.php"><i class="fa-solid fa-cart-shopping"></i> Cart (<?php echo $cartCount; ?>)</a></li>
        <li><a href="aboutus.php"><i class="fa-solid fa-circle-info"></i> About Us</a></li>
        <li><a href="../logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
    </ul>
</nav>

<div class="container">

    <div class="page-header">
        <h1 class="page-title"><i class="fa-solid fa-store"></i> Available Products</h1>
        <p class="page-subtitle">Browse our selection of products</p>

        <div style="margin-top:10px;">
            <a href="?category=All" class="filter-btn <?php echo ($filter=='All')?'filter-active':''; ?>">
                <i class="fa-solid fa-border-all"></i> All
            </a>
            <a href="?category=Food" class="filter-btn <?php echo ($filter=='Food')?'filter-active':''; ?>">
                <i class="fa-solid fa-utensils"></i> Food
            </a>
            <a href="?category=Drinks" class="filter-btn <?php echo ($filter=='Drinks')?'filter-active':''; ?>">
                <i class="fa-solid fa-glass-water"></i> Drinks
            </a>
            <a href="?category=Beverages" class="filter-btn <?php echo ($filter=='Beverages')?'filter-active':''; ?>">
                <i class="fa-solid fa-mug-hot"></i> Beverages
            </a>
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
                <img src="../admin/<?php echo htmlspecialchars($product['image']); ?>"
                     alt="<?php echo htmlspecialchars($product['name']); ?>">

                <div class="product-card-body">
                    <h3 class="product-card-title">
                        <?php echo htmlspecialchars($product['name']); ?>
                    </h3>

                    <small style="color:gray;">
                        <i class="fa-solid fa-folder-open"></i> <?php echo htmlspecialchars($product['category'] ?? 'Food'); ?>
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
                                <i class="fa-solid fa-cart-plus"></i> Add to Cart
                            </button>
                        </form>

                        <form action="process.php" method="POST" style="flex:1;">
                            <input type="hidden" name="action" value="buy_now">
                            <input type="hidden" name="product_id" value="<?php echo $id; ?>">
                            <button type="submit" class="btn btn-primary btn-sm btn-block">
                                <i class=" "></i> Buy Now
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