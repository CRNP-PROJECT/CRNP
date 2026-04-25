<?php
session_start();

if(!isset($_SESSION['cashier_email'])){
    header("Location: cashier_login.php");
    exit;
}

include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

$rdb = new firebaseRDB($databaseURL);

$filter = $_GET['category'] ?? "All";

$products_raw = $rdb->retrieve("/products");
$data = json_decode($products_raw, true) ?? [];

$products = [];

foreach ($data as $id => $product) {
    $category = $product['category'] ?? 'Food';

    if ($filter === "All" || $category === $filter) {
        $products[$id] = $product;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<!-- ICONS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<link rel="stylesheet" href="../styles.css">

<title>Cashier POS</title>
</head>

<body class="cashier-pos">

<!-- ================= NAVBAR ================= -->
<nav class="navbar">

    <div class="navbar-brand-container">
        <img src="../img/logo.png" class="logo" alt="Logo">
    </div>

    <ul class="navbar-menu">

        <li>
            <a href="cashier_index.php">
                <i class="fa-solid fa-chart-line"></i> Dashboard
            </a>
        </li>

        <li>
            <a href="create_order.php">
                <i class="fa-solid fa-bag-shopping"></i> New Order
            </a>
        </li>

        <li>
            <a href="view_orders.php">
                <i class="fa-solid fa-file-invoice"></i> Orders
            </a>
        </li>

        <li>
            <a href="view_bookings.php">
                <i class="fa-solid fa-calendar-days"></i> Bookings
            </a>
        </li>

        <li>
            <a href="cashier_logout.php">
                <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
            </a>
        </li>

    </ul>
</nav>

<!-- ================= HEADER ================= -->
<div class="pos-header">
    <h1><i class="fa-solid fa-cash-register"></i> POS System</h1>
    <p><?php echo htmlspecialchars($_SESSION['cashier_email']); ?></p>
</div>

<!-- ================= FILTER ================= -->
<div class="pos-filters">

    <a href="?category=All" class="filter-btn <?= ($filter=='All')?'active':'' ?>">
        <i class="fa-solid fa-layer-group"></i> All
    </a>

    <a href="?category=Food" class="filter-btn <?= ($filter=='Food')?'active':'' ?>">
        <i class="fa-solid fa-burger"></i> Food
    </a>

    <a href="?category=Drinks" class="filter-btn <?= ($filter=='Drinks')?'active':'' ?>">
        <i class="fa-solid fa-glass-water"></i> Drinks
    </a>

    <a href="?category=Beverages" class="filter-btn <?= ($filter=='Beverages')?'active':'' ?>">
        <i class="fa-solid fa-mug-hot"></i> Beverages
    </a>

</div>

<!-- ================= MAIN ================= -->
<div class="pos-container">

    <!-- PRODUCTS -->
    <div class="products">

        <?php foreach($products as $id => $product): ?>
        <div class="product-card"
            onclick="addToCart('<?= $id ?>','<?= $product['name'] ?>',<?= $product['price'] ?>)">

            <img src="../admin/<?= htmlspecialchars($product['image']) ?>" class="product-img">

            <div class="product-info">
                <strong><?= htmlspecialchars($product['name']) ?></strong>
                <p>₱<?= number_format($product['price'], 2) ?></p>
                <small><?= $product['category'] ?? 'Food' ?></small>
            </div>

        </div>
        <?php endforeach; ?>

    </div>

    <!-- CART -->
    <div class="cart">

        <h3><i class="fa-solid fa-cart-shopping"></i> Cart</h3>

        <div id="cartBox"></div>

        <div class="total">
            Total: ₱<span id="total">0.00</span>
        </div>

        <input type="text" id="customer_name" placeholder="Customer Name">
        <input type="text" id="table_number" placeholder="Table Number">

        <input type="hidden" id="payment_method" value="Over the Counter">

        <form method="POST" action="save_order.php">

            <input type="hidden" name="cart_data" id="cart_data">
            <input type="hidden" name="customer_name" id="c_name">
            <input type="hidden" name="table_number" id="c_table">
            <input type="hidden" name="payment_method" id="c_payment">

            <button type="submit" onclick="prepareOrder()">
                <i class="fa-solid fa-check"></i> Place Order
            </button>

        </form>

    </div>

</div>

<!-- ================= SCRIPT ================= -->
<script>

let cart = {};

function addToCart(id, name, price) {
    if(cart[id]) {
        cart[id].qty += 1;
    } else {
        cart[id] = { name, price, qty: 1 };
    }
    renderCart();
}

function removeItem(id){
    delete cart[id];
    renderCart();
}

function renderCart() {

    let box = document.getElementById("cartBox");
    box.innerHTML = "";

    let total = 0;

    for(let id in cart) {

        let item = cart[id];
        let subtotal = item.price * item.qty;
        total += subtotal;

        box.innerHTML += `
        <div class="cart-item">
            <div>
                ${item.name} x ${item.qty}
            </div>

            <div class="cart-right">
                ₱${subtotal.toFixed(2)}
                <button class="remove-btn" onclick="removeItem('${id}')">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </div>
        </div>`;
    }

    document.getElementById("total").innerText = total.toFixed(2);
    document.getElementById("cart_data").value = JSON.stringify(cart);
}

function prepareOrder() {

    document.getElementById("c_name").value =
        document.getElementById("customer_name").value;

    document.getElementById("c_table").value =
        document.getElementById("table_number").value;

    document.getElementById("c_payment").value =
        document.getElementById("payment_method").value;

    if(Object.keys(cart).length === 0){
        alert("Cart is empty!");
        event.preventDefault();
    }
}

</script>

</body>
</html>