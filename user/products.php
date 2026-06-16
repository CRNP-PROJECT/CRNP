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

$filter = $_GET['category'] ?? "All";

$products = [];
$productNames = [];

// FETCH PRODUCTS
try {
    $retrieve = $rdb->retrieve("/products");
    $data = json_decode($retrieve, true);

    if (is_array($data)) {
        foreach ($data as $id => $product) {
            $category = $product['category'] ?? 'Food';

            if ($filter === "All" || $category === $filter) {
                $products[$id] = $product;
                $productNames[] = $product['name'];
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
</head>

<body class="products-page-body">

<!-- NAVBAR -->
<header class="navbar">

    <div class="navbar-brand-container">
        <img src="../img/logo.png" class="logo">
    </div>

    <div class="navbar-right">

        <ul class="navbar-menu">
            <li><a href="index.php">Home</a></li>
            <li><a href="products.php" class="active">Products</a></li>
            <li><a href="booking.php">Booking</a></li>

            <!-- 🔥 REALTIME CART COUNT -->
            <li>
                <a href="cart.php">
                    Cart (<span id="cart-count"><?php echo $cartCount; ?></span>)
                </a>
            </li>

            
            <li><a href="aboutus.php">About</a></li>
        </ul>

        <!-- SEARCH -->
        <form action="products.php" method="GET" class="search-box" style="position: relative;">
            <button type="submit" style="background:none; border:none; cursor:pointer; color:inherit;">
                <i class="fa-solid fa-magnifying-glass"></i>
            </button>

            <input type="text" name="search" placeholder="Search..." class="navbar-search" autocomplete="off">

            <div id="suggestion-box"></div>
        </form>

        <!-- USER -->
        <div class="navbar-dropdown">
            <span class="navbar-user-btn">
                <i class="fa-regular fa-user"></i>
                <?php echo htmlspecialchars($username); ?>
            </span>

            <div class="navbar-dropdown-content">
                <a href="your_profile.php">My Profile</a>
                <a href="your_orders.php">Your Orders</a>
                <a href="../logout.php">Logout</a>
            </div>
        </div>

    </div>
</header>

<!-- PRODUCTS -->
<div class="product-scoped">

    <div class="page-header">
        <h1 class="page-title">AVAILABLE PRODUCTS</h1>

        <div class="filter-container">
            <a href="?category=All" class="filter-btn <?php echo ($filter=='All')?'filter-active':''; ?>">All</a>
            <a href="?category=Food" class="filter-btn <?php echo ($filter=='Food')?'filter-active':''; ?>">Food</a>
            <a href="?category=Drinks" class="filter-btn <?php echo ($filter=='Drinks')?'filter-active':''; ?>">Alcohol</a>
            <a href="?category=Beverages" class="filter-btn <?php echo ($filter=='Beverages')?'filter-active':''; ?>">Beverages</a>
        </div>
    </div>

    <div class="product-grid">

        <?php foreach($products as $id => $product): ?>
        <div class="product-card">

            <img src="../admin/<?php echo htmlspecialchars($product['image']); ?>">

            <div class="product-card-body">

                <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                <p>₱<?php echo number_format($product['price'], 2); ?></p>

                <!-- ADD TO CART -->
                <form class="add-to-cart-form" method="POST">
                    <input type="hidden" name="action" value="add_to_cart">
                    <input type="hidden" name="product_id" value="<?php echo $id; ?>">

                    <button type="submit" class="btn">
                        <i ></i> Add to Cart
                    </button>
                </form>

                <!-- BUY NOW -->
                <form action="process.php" method="POST">
                    <input type="hidden" name="action" value="buy_now">
                    <input type="hidden" name="product_id" value="<?php echo $id; ?>">

                    <button type="submit" class="btn">
                        Buy Now
                    </button>
                </form>

            </div>
        </div>
        <?php endforeach; ?>

    </div>
</div>

<!-- TOAST STYLE -->
<style>
.toast {
    position: fixed;
    bottom: 20px;
    right: 20px;
    background: #c5a072;
    color: #000;
    padding: 12px 18px;
    border-radius: 8px;
    font-weight: 500;
    z-index: 9999;
    opacity: 0;
    transition: 0.3s;
}
</style>

<!-- SCRIPT -->
<script>
const allProducts = <?php echo json_encode($productNames); ?>;
const input = document.querySelector('.navbar-search');
const box = document.getElementById('suggestion-box');

// SEARCH
input.addEventListener('input', function () {
    const val = this.value.trim().toLowerCase();
    box.innerHTML = '';

    if (val.length < 2) {
        box.style.display = 'none';
        return;
    }

    const matches = allProducts.filter(p => p.toLowerCase().includes(val));

    matches.forEach(m => {
        const item = document.createElement('div');
        item.innerText = m;
        item.style.padding = "8px";
        item.style.cursor = "pointer";
        item.style.background = "white";
        item.style.color = "black";

        item.onclick = () => {
            input.value = m;
            input.form.submit();
        };

        box.appendChild(item);
    });

    box.style.display = matches.length ? 'block' : 'none';
});

// CLOSE DROPDOWN
document.addEventListener('click', e => {
    if (!input.contains(e.target) && !box.contains(e.target)) {
        box.style.display = 'none';
    }
});

// AJAX ADD TO CART (REALTIME COUNT)
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

            if (data.trim() === "success") {

                let cart = document.getElementById("cart-count");
                if (cart) {
                    cart.innerText = parseInt(cart.innerText || 0) + 1;
                }

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

// TOAST
function showToast(msg) {
    let t = document.createElement("div");
    t.className = "toast";
    t.innerText = msg;

    document.body.appendChild(t);

    setTimeout(() => t.style.opacity = "1", 50);

    setTimeout(() => {
        t.style.opacity = "0";
        setTimeout(() => t.remove(), 300);
    }, 2000);
}
</script>

</body>
</html>