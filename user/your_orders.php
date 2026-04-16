<?php
session_start();

include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

$rdb = new firebaseRDB($databaseURL);

$user_email = $_SESSION['email'] ?? '';

// ================= FETCH ORDERS =================
$orders_raw = $rdb->retrieve("/orders");
$orders = json_decode($orders_raw, true) ?? [];

// ================= FILTER USER ORDERS =================
$user_orders = [];

foreach($orders as $id => $order){

    $order_email = $order['email'] 
        ?? $order['user_email'] 
        ?? $order['cashier'] 
        ?? '';

    if($order_email === $user_email){
        $user_orders[$id] = $order;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="../styles.css">
<title>Your Orders</title>
</head>

<body class="order-confirmation-body">

<nav class="navbar">
    <div class="navbar-brand-container">
        <img src="../img/logo.png" alt="Logo" class="logo">
    </div>

    <ul class="navbar-menu">
        <li><a href="index.php"><i class="fa-solid fa-house"></i> Home</a></li>
        <li><a href="products.php"><i class="fa-solid fa-shop"></i> Products</a></li>
        <li><a href="booking.php"><i class="fa-solid fa-calendar-check"></i> Booking</a></li>
        <li><a href="cart.php"><i class="fa-solid fa-cart-shopping"></i> Cart</a></li>
        <li><a href="../logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
    </ul>
</nav>

<div class="confirmation-wrapper">

<div class="container-sm">

<!
<!-- ================= ORDER HISTORY ================= -->
<div class="card mt-3">

    <h2 style="color:#f3e5ab;">📦 Your Order History</h2>

    <?php if(empty($user_orders)): ?>
        <p class="text-muted">No orders found.</p>
    <?php else: ?>

        <?php foreach($user_orders as $id => $order): ?>

            <div style="border-bottom:1px solid #ccc; padding:10px 0;">

                
                <p><b>Name:</b> <?= htmlspecialchars($order['full_name'] ?? $order['customer_name'] ?? '') ?></p>
                <p><b>Total:</b> ₱<?= number_format($order['total'] ?? 0, 2) ?></p>
                <p><b>Status:</b> <?= strtoupper($order['status'] ?? 'pending') ?></p>

                <details>
                    <summary>View Items</summary>

                    <?php 
                    $products = $order['products'] ?? $order['items'] ?? [];

                    foreach($products as $item): ?>
                        <p>
                            <?= htmlspecialchars($item['name']) ?>
                            x <?= intval($item['qty']) ?>
                        </p>
                    <?php endforeach; ?>

                </details>

            </div>

        <?php endforeach; ?>

    <?php endif; ?>

</div>

</div>
</div>

</body>
</html>