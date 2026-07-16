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
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../styles.css">
<title>Cashier POS</title>
</head>

<body class="create-order">

<header class="navbar">
    <div class="navbar-brand-container">
        <img src="../img/logo.png" class="logo">
    </div>

    <div class="navbar-right">
        <ul class="navbar-menu">
            <li><a href="cashier_index.php">Dashboard</a></li>
            <li><a href="create_order.php" class="active">Create Order</a></li>
            <li><a href="view_orders.php">Orders</a></li>
            <li><a href="view_bookings.php">Bookings</a></li>
            <li><a href="cashier_orderHistory.php">History</a></li>
            <li><a href="cashier_logout.php">Logout</a></li>
        </ul>
    </div>
</header>

<div class="create-order-wrapper">

    <div class="create-order-header">
        <h1>Diner POS Portal</h1>
        <p><?= htmlspecialchars($_SESSION['cashier_email']); ?></p>
    </div>

    <!-- FILTER -->
    <div class="create-order-filters">
        <a href="?category=All" class="<?= ($filter=='All')?'active':'' ?>">All</a>
        <a href="?category=Food" class="<?= ($filter=='Food')?'active':'' ?>">Food</a>
        <a href="?category=Drinks" class="<?= ($filter=='Drinks')?'active':'' ?>">Drinks</a>
        <a href="?category=Beverages" class="<?= ($filter=='Beverages')?'active':'' ?>">Beverages</a>
    </div>

    <div class="create-order-layout">

        <!-- PRODUCTS -->
        <div class="create-order-products">
            <?php foreach($products as $id => $product): ?>
            <div class="create-order-card"
                onclick="addToCart(
                    '<?= $id ?>',
                    '<?= $product['name'] ?>',
                    <?= $product['price'] ?>,
                    '<?= $product['category'] ?>'
                )">

                <img src="<?= htmlspecialchars($product['image']) ?>">

                <div class="create-order-info">
                    <h4><?= htmlspecialchars($product['name']) ?></h4>
                    <p>₱<?= number_format($product['price'], 2) ?></p>
                    <span><?= $product['category'] ?></span>
                </div>

            </div>
            <?php endforeach; ?>
        </div>

        <!-- CART -->
        <div class="create-order-cart">
            <h3>Current Cart</h3>

            <div id="cartBox"></div>

            <div class="create-order-total">
                ₱<span id="total">0.00</span>
            </div>

            <input type="text" id="customer_name" placeholder="Customer Name">
            <input type="text" id="table_number" placeholder="Table Number">

            <form method="POST" action="save_order.php">
                <input type="hidden" name="cart_data" id="cart_data">
                <input type="hidden" name="customer_name" id="c_name">
                <input type="hidden" name="table_number" id="c_table">
                <input type="hidden" name="payment_method" id="c_payment" value="Over the Counter">

                <button type="submit" onclick="prepareOrder()">Place Order</button>
            </form>
        </div>

    </div>
</div>

<script>

let cart = {};

function addToCart(id, name, price, category) {

    if(cart[id]) {
        cart[id].qty += 1;
    } else {
        cart[id] = {
            name: name,
            price: price,
            qty: 1,
            category: category   // ✅ IMPORTANT FIX
        };
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

    document.getElementById("c_payment").value = "Over the Counter";

    if(Object.keys(cart).length === 0){
        alert("Cart is empty!");
        event.preventDefault();
    }
}

</script>

</body>
</html>