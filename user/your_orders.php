<?php
session_start();

include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

$rdb = new firebaseRDB($databaseURL);

$user_email = $_SESSION['email'] ?? '';
$username = $_SESSION['username'] ?? 'User';

if(!$user_email){
    header("Location: login.php");
    exit;
}

/* ================= FETCH ORDERS ================= */
$orders_raw = $rdb->retrieve("/orders");
$orders = json_decode($orders_raw, true) ?? [];

/* ================= FILTER USER ORDERS ================= */
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

<body class="your-orders-body">

<!-- ================= NAVBAR ================= -->
<nav class="navbar">
    <div class="navbar-brand-container">
        <img src="../img/logo.png" class="logo" alt="Logo">
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
                <?= htmlspecialchars($username) ?> ▼
            </a>

            <div class="navbar-dropdown-content">
                <a href="your_profile.php"><i class="fa-solid fa-id-card"></i> Profile</a>
                <a href="your_orders.php"><i class="fa-solid fa-box"></i> Orders</a>
                <a href="../logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </div>
        </li>
    </ul>
</nav>

<!-- ================= MAIN CONTENT ================= -->
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

                <!-- HEADER -->
                <div class="your-orders-header">
                    <strong>
                        <i class="fa-solid fa-user"></i>
                        <?= htmlspecialchars($order['full_name'] ?? 'Unknown') ?>
                    </strong>

                    <span class="your-orders-badge your-orders-<?= $status ?>">
                        <?= strtoupper($status) ?>
                    </span>
                </div>

                <!-- TOTAL -->
                <div class="your-orders-info">
                    <i class="fa-solid fa-peso-sign"></i>
                    ₱<?= number_format($order['total'] ?? 0, 2) ?>
                </div>

                <!-- ITEMS -->
                <div class="your-orders-items">
                    <div class="your-orders-items-title">
                        <i class="fa-solid fa-list"></i> Items
                    </div>

                    <?php
                    $products = $order['products'] ?? $order['items'] ?? [];

                    if(is_array($products)):
                        foreach($products as $item):
                    ?>
                        <div class="your-orders-item">
                            <span>
                                <i class="fa-solid fa-bowl-food"></i>
                                <?= htmlspecialchars($item['name'] ?? 'Item') ?>
                            </span>

                            <span>
                                x <?= intval($item['qty'] ?? 1) ?>
                            </span>
                        </div>
                    <?php
                        endforeach;
                    endif;
                    ?>

                </div>

            </div>

        <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>
</div>

</body>
</html>