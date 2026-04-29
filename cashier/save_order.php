<?php
session_start();
include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

$rdb = new firebaseRDB($databaseURL);

// ================= GET DATA =================
$cart_data = $_POST['cart_data'] ?? '';
$customer_name = $_POST['customer_name'] ?? '';
$table_number = $_POST['table_number'] ?? '';
$payment_method = $_POST['payment_method'] ?? 'Cash';

if(!$cart_data || !$customer_name || !$table_number){
    die("Missing order data");
}

$cart = json_decode($cart_data, true);

if(empty($cart)){
    die("Cart is empty");
}

// ================= CALCULATE TOTAL =================
$total = 0;
foreach($cart as $item){
    $total += $item['price'] * $item['qty'];
}

// ================= CREATE ORDER =================
$order_id = uniqid("ORD-");

$order = [
    "order_id" => $order_id,
    "full_name" => $customer_name,
    "table_number" => $table_number,
    "payment_method" => $payment_method,
    "products" => $cart,
    "total" => $total,
    "status" => "accepted",
    "kitchen_status" => "pending",
    "created_at" => date("Y-m-d H:i:s"),
    "cashier" => $_SESSION['cashier_email']
];

// ================= SAVE =================
$rdb->insert("/orders", $order);

// ================= STORE =================
$_SESSION['last_order'] = $order;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Receipt</title>
<link rel="stylesheet" href="../styles.css">
</head>

<body class="create-order-success">

<header class="navbar">

    <div class="navbar-brand-container">
        <img src="../img/logo.png" class="logo" alt="Logo">
    </div>

    <div class="navbar-right">
        <ul class="navbar-menu">
            <li><a href="cashier_index.php">Dashboard</a></li>
            <li><a href="create_order.php">Create Orders</a></li>
            <li><a href="view_orders.php">Orders</a></li>
            <li><a href="view_bookings.php">Bookings</a></li>
            <li><a href="cashier_orderHistory.php">History</a></li>
            <li><a href="cashier_logout.php">Logout</a></li>
        </ul>
    </div>

</header>

<!-- ================= RECEIPT ================= -->
<div class="create-order-success-wrapper">
<div class="create-order-success-card">

<h2>ORDER SUCCESSFUL</h2>
<h3>RECEIPT</h3>

<p><b>Order ID:</b> <?= htmlspecialchars($order['order_id']) ?></p>
<p><b>Customer:</b> <?= htmlspecialchars($order['full_name']) ?></p>
<p><b>Table:</b> <?= htmlspecialchars($order['table_number']) ?></p>
<p><b>Payment:</b> <?= htmlspecialchars($order['payment_method']) ?></p>
<p><b>Status:</b> <?= htmlspecialchars($order['status']) ?></p>

<hr>

<h3>Items</h3>

<div class="create-order-success-items">
<?php foreach($order['products'] as $item): ?>
    <p>
        <?= htmlspecialchars($item['name']) ?> x<?= intval($item['qty']) ?>
        <span>₱<?= number_format($item['price'] * $item['qty'], 2) ?></span>
    </p>
<?php endforeach; ?>
</div>

<br>

<div class="create-order-success-total">
    <span>Total:</span>
    <span>₱<?= number_format($order['total'], 2) ?></span>
</div>

<br>

<button onclick="window.print()">Print Receipt</button>

<br><br>

<a href="cashier_index.php">Back to POS</a>

</div>
</div>

</body>
</html>