<?php
session_start();

include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}

/* 🔥 FIX: missing variable */
$username = $_SESSION['username'] ?? "User";

$rdb = new firebaseRDB($databaseURL);

// CATEGORY FILTER
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
    padding: 8px 15px;
    border: 1px solid #ccc;
    border-radius: 5px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.filter-active {
    background: #80461B;
    color: white;
    border-color: #80461B;
}

.btn i {
    margin-right: 5px;
}
</style>

</head>

<body class="products-page-body">

<!-- NAVBAR -->
<nav class="navbar">
    <div class="navbar-brand-container">
        <img src="../img/logo.png" alt="Logo" class="logo">
        <a href="index.php" class="navbar-brand"></a>
    </div>

    <ul class="navbar-menu">

        <li><a href="index.php"><i class="fa-solid fa-house"></i> Home</a></li>

        <li><a href="products.php" class="active"><i class="fa-solid fa-shop"></i> Products</a></li>

        <li><a href="booking.php"><i class="fa-solid fa-calendar-check"></i> Booking</a></li>

        <li><a href="cart.php"><i class="fa-solid fa-cart-shopping"></i> Cart (<?php echo $cartCount; ?>)</a></li>

        <li><a href="your_orders.php"><i class="fa-solid fa-box-open"></i> Your Order</a></li>

        <li><a href="aboutus.php"><i class="fa-solid fa-circle-info"></i> About Us</a></li>

        <!-- USER -->
        <li class="navbar-dropdown">
            <a href="#">
                <i class="fa-solid fa-user"></i>
                <?php echo htmlspecialchars($username); ?> ▼
            </a>

            <div class="navbar-dropdown-content">
                <a href="your_profile.php"><i class="fa-solid fa-id-card"></i> My Profile</a>
                <a href="your_orders.php"><i class="fa-solid fa-box"></i> Your Orders</a>
                <a href="../logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </div>
        </li>

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

                <img src="../admin/<?php echo htmlspecialchars($product['image']); ?>">

                <div class="product-card-body">

                    <h3 class="product-card-title">
                        <?php echo htmlspecialchars($product['name']); ?>
                    </h3>

                    <p class="product-card-price">
                        ₱<?php echo number_format($product['price'], 2); ?>
                    </p>

                    <p class="product-card-desc">
                        <?php echo htmlspecialchars($product['description']); ?>
                    </p>

                    <!-- AJAX FORM -->
                    <form class="add-to-cart-form" method="POST">
                        <input type="hidden" name="action" value="add_to_cart">
                        <input type="hidden" name="product_id" value="<?php echo $id; ?>">

                        <button type="submit" class="btn btn-secondary btn-sm btn-block">
                            <i class="fa-solid fa-cart-plus"></i> Add to Cart
                        </button>
                    </form>

                    <!-- BUY NOW -->
                    <form action="process.php" method="POST">
                        <input type="hidden" name="action" value="buy_now">
                        <input type="hidden" name="product_id" value="<?php echo $id; ?>">

                        <button type="submit" class="btn btn-primary btn-sm btn-block">
                            Buy Now
                        </button>
                    </form>

                </div>
            </div>
            <?php endforeach; ?>

        </div>
    <?php endif; ?>
</div>

<!-- AJAX + TOAST -->
<script>
document.addEventListener("DOMContentLoaded", function () {

    document.querySelectorAll(".add-to-cart-form").forEach(form => {
        form.addEventListener("submit", function (e) {
            e.preventDefault();

            const formData = new FormData(this);

            fetch("process.php", {
                method: "POST",
                body: formData
            })
            .then(res => res.text())
            .then(data => {
                console.log(data);

                if (data.trim() === "success") {
                    showToast("🛒 Added to cart!");
                } else {
                    showToast("⚠️ Failed to add item");
                }
            })
            .catch(() => {
                showToast("❌ Server error");
            });
        });
    });

    function showToast(message) {
        let toast = document.createElement("div");
        toast.innerText = message;

        toast.style.position = "fixed";
        toast.style.bottom = "20px";
        toast.style.right = "20px";
        toast.style.background = "#80461B";
        toast.style.color = "white";
        toast.style.padding = "12px 18px";
        toast.style.borderRadius = "8px";
        toast.style.zIndex = "9999";
        toast.style.opacity = "0";
        toast.style.transition = "0.3s";

        document.body.appendChild(toast);

        setTimeout(() => toast.style.opacity = "1", 50);

        setTimeout(() => {
            toast.style.opacity = "0";
            setTimeout(() => toast.remove(), 300);
        }, 2000);
    }

});
</script>

</body>
</html>