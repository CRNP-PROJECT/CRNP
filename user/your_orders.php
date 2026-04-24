<?php
session_start();

include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

$rdb = new firebaseRDB($databaseURL);

$user_email = $_SESSION['email'] ?? '';
<<<<<<< HEAD
$username = $_SESSION['username'] ?? 'User';

if(!$user_email){
    header("Location: login.php");
    exit;
}

// ================= FETCH ORDERS =================
$orders_raw = $rdb->retrieve("/orders");
$orders = json_decode($orders_raw, true);

if(!is_array($orders)){
    $orders = [];
}
=======

// ================= FETCH ORDERS =================
$orders_raw = $rdb->retrieve("/orders");
$orders = json_decode($orders_raw, true) ?? [];
>>>>>>> 784d58b7356ff90b699f7f25dfe2dd02149d3401

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
<<<<<<< HEAD
=======
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="../styles.css">
<title>Your Orders</title>
</head>

<body class="order-confirmation-body">
>>>>>>> 784d58b7356ff90b699f7f25dfe2dd02149d3401

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="../styles.css">

<title>Your Orders</title>
</head>

<body class="your-orders-body">

<!-- NAVBAR -->
<nav class="navbar">
    <div class="navbar-brand-container">
        <img src="../img/logo.png" class="logo">
        <a href="index.php" class="navbar-brand"></a>
    </div>

    <ul class="navbar-menu">

        <li><a href="index.php"><i class="fa-solid fa-house"></i> Home</a></li>
        <li><a href="products.php"><i class="fa-solid fa-shop"></i> Products</a></li>
        <li><a href="booking.php"><i class="fa-solid fa-calendar-check"></i> Booking</a></li>
        <li><a href="cart.php"><i class="fa-solid fa-cart-shopping"></i> Cart</a></li>
        <li><a href="your_orders.php" class="active"><i class="fa-solid fa-box-open"></i> Orders</a></li>
        <li><a href="aboutus.php"><i class="fa-solid fa-circle-info"></i> About</a></li>

        <li class="navbar-dropdown">
            <a href="#">
                <i class="fa-solid fa-user"></i>
                <?php echo htmlspecialchars($username); ?> ▼
            </a>

            <div class="navbar-dropdown-content">
                <a href="your_profile.php"><i class="fa-solid fa-id-card"></i> Profile</a>
                <a href="your_orders.php"><i class="fa-solid fa-box"></i> Orders</a>
                <a href="../logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </div>
        </li>

    </ul>
</nav>

<<<<<<< HEAD
<!-- MAIN -->
<div class="your-orders-wrapper">
<div class="your-orders-container">

    <h1 class="your-orders-title">
        <i class="fa-solid fa-box-open"></i> Your Orders
    </h1>

    <?php if(empty($user_orders)): ?>
        <p class="your-orders-empty">
            <i class="fa-regular fa-face-frown"></i> No orders found.
        </p>
    <?php else: ?>

        <div class="your-orders-grid">

        <?php foreach($user_orders as $id => $order): ?>

            <?php $status = strtolower($order['status'] ?? 'pending'); ?>

            <div class="your-orders-card">

                <div class="your-orders-header">
                    <strong>
                        <i class="fa-solid fa-user"></i>
                        <?= htmlspecialchars($order['full_name'] ?? 'Unknown') ?>
                    </strong>

                    <span class="your-orders-badge your-orders-<?= $status ?>">
                        <?= strtoupper($status) ?>
                    </span>
                </div>

                <div class="your-orders-info">
                    <i class="fa-solid fa-peso-sign"></i>
                    ₱<?= number_format($order['total'] ?? 0, 2) ?>
                </div>

                <div class="your-orders-items">

                    <div class="your-orders-items-title">
                        <i class="fa-solid fa-list"></i> Items
                    </div>
=======
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
>>>>>>> 784d58b7356ff90b699f7f25dfe2dd02149d3401

                    <?php 
                    $products = $order['products'] ?? $order['items'] ?? [];

<<<<<<< HEAD
                    if(is_array($products)):
                        foreach($products as $item): ?>
                            <div class="your-orders-item">
                                <span>
                                    <i class="fa-solid fa-bowl-food"></i>
                                    <?= htmlspecialchars($item['name'] ?? 'Item') ?>
                                </span>
                                <span>x <?= intval($item['qty'] ?? 1) ?></span>
                            </div>
                    <?php endforeach; endif; ?>

                </div>
=======
                    foreach($products as $item): ?>
                        <p>
                            <?= htmlspecialchars($item['name']) ?>
                            x <?= intval($item['qty']) ?>
                        </p>
                    <?php endforeach; ?>

                </details>
>>>>>>> 784d58b7356ff90b699f7f25dfe2dd02149d3401

            </div>

        <?php endforeach; ?>

<<<<<<< HEAD
        </div>

    <?php endif; ?>

</div>
=======
    <?php endif; ?>

</div>

</div>
>>>>>>> 784d58b7356ff90b699f7f25dfe2dd02149d3401
</div>

</body>
</html>