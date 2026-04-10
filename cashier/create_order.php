<?php
session_start();

if(!isset($_SESSION['cashier_email'])){
    header("Location: cashier_login.php");
    exit;
}

include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

$rdb = new firebaseRDB($databaseURL);

// CATEGORY FILTER
$filter = $_GET['category'] ?? "All";

// FETCH PRODUCTS
$products_raw = $rdb->retrieve("/products");
$data = json_decode($products_raw, true) ?? [];

$products = [];

foreach ($data as $id => $product) {
    $category = $product['category'] ?? 'Food';

    if ($filter === "All" || $category === $category) {
        $products[$id] = $product;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>POS System</title>

<style>
body {
    font-family: Arial;
}

.container {
    display: flex;
    gap: 20px;
    padding: 20px;
}

.products {
    width: 65%;
}

.product-card {
    border: 1px solid #ddd;
    padding: 10px;
    margin-bottom: 10px;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
}

.product-card:hover {
    background: #f5f5f5;
}

.cart {
    width: 35%;
    border: 1px solid #ccc;
    padding: 15px;
    border-radius: 10px;
    height: 80vh;
    overflow-y: auto;
}

.cart-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
}

.total {
    font-size: 22px;
    font-weight: bold;
    margin-top: 20px;
}

button {
    width: 100%;
    padding: 10px;
    margin-top: 10px;
    background: green;
    color: white;
    border: none;
    cursor: pointer;
}

.filter-btn {
    margin-right: 5px;
    padding: 6px 12px;
    border: 1px solid #ccc;
    text-decoration: none;
    border-radius: 5px;
}

.filter-active {
    background: #007bff;
    color: white;
}
</style>

</head>
<body>

<h2 style="padding:20px;">POS Cashier System</h2>

<!-- FILTER -->
<div style="padding:0 20px;">
    <a href="?category=All" class="filter-btn <?php echo ($filter=='All')?'filter-active':''; ?>">All</a>
    <a href="?category=Food" class="filter-btn <?php echo ($filter=='Food')?'filter-active':''; ?>">Food</a>
    <a href="?category=Drinks" class="filter-btn <?php echo ($filter=='Drinks')?'filter-active':''; ?>">Drinks</a>
    <a href="?category=Beverages" class="filter-btn <?php echo ($filter=='Beverages')?'filter-active':''; ?>">Beverages</a>
</div>

<div class="container">

<!-- LEFT: PRODUCTS -->
<div class="products">

<?php foreach($products as $id => $product): ?>
    <div class="product-card"
        onclick="addToCart('<?php echo $id; ?>','<?php echo $product['name']; ?>',<?php echo $product['price']; ?>)">

        <img src="../admin/<?php echo htmlspecialchars($product['image']); ?>" width="60">

        <div>
            <strong><?php echo htmlspecialchars($product['name']); ?></strong><br>
            ₱<?php echo number_format($product['price'], 2); ?><br>
            <small><?php echo $product['category'] ?? 'Food'; ?></small>
        </div>

    </div>
<?php endforeach; ?>

</div>

<!-- RIGHT: CART -->
<div class="cart">

<h3>Cart</h3>

<div id="cartBox"></div>

<div class="total">
Total: ₱<span id="total">0.00</span>
</div>

<!-- CUSTOMER INFO -->
<input type="text" id="customer_name" placeholder="Customer Name" style="width:100%; padding:8px; margin-top:10px;"><br>

<input type="text" id="table_number" placeholder="Table Number" style="width:100%; padding:8px; margin-top:10px;"><br>

<!-- PAYMENT FIXED -->
<input type="hidden" id="payment_method" value="Over the Counter">

<form method="POST" action="save_order.php">

    <input type="hidden" name="cart_data" id="cart_data">
    <input type="hidden" name="customer_name" id="c_name">
    <input type="hidden" name="table_number" id="c_table">
    <input type="hidden" name="payment_method" id="c_payment">

    <button type="submit" onclick="prepareOrder()">Place Order</button>

</form>

</div>

</div>

<script>

let cart = {};

function addToCart(id, name, price) {

    if(cart[id]) {
        cart[id].qty += 1;
    } else {
        cart[id] = {
            name: name,
            price: price,
            qty: 1
        };
    }

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
            <div>
                ₱${subtotal.toFixed(2)}
            </div>
        </div>`;
    }

    document.getElementById("total").innerText = total.toFixed(2);

    document.getElementById("cart_data").value = JSON.stringify(cart);
}

function prepareOrder() {

    document.getElementById("c_name").value = document.getElementById("customer_name").value;
    document.getElementById("c_table").value = document.getElementById("table_number").value;
    document.getElementById("c_payment").value = document.getElementById("payment_method").value;

    if(Object.keys(cart).length === 0){
        alert("Cart is empty!");
        event.preventDefault();
        return false;
    }

    if(document.getElementById("customer_name").value === "" ||
       document.getElementById("table_number").value === ""){
        alert("Fill customer info!");
        event.preventDefault();
        return false;
    }
}

</script>

</body>
</html>