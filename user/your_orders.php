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

/* ================= FILTER INPUTS ================= */
$type_filter = $_GET['type'] ?? 'orders';
$status_filter = $_GET['status'] ?? 'all';

/* ================= FETCH DATA ================= */
$orders = json_decode($rdb->retrieve("/orders"), true) ?? [];
$bookings = json_decode($rdb->retrieve("/bookings"), true) ?? [];

/* ================= FILTER FUNCTION ================= */
function filterData($data, $email, $status_filter){
    $result = [];

    foreach($data as $id => $item){

        $item_email = $item['email']
            ?? $item['user_email']
            ?? '';

        if($item_email !== $email) continue;

        $status = strtolower($item['status'] ?? 'pending');

        if($status_filter !== 'all' && $status !== $status_filter){
            continue;
        }

        $result[$id] = $item;
    }

    return $result;
}

/* ================= APPLY FILTER ================= */
$user_orders = filterData($orders, $user_email, $status_filter);
$user_bookings = filterData($bookings, $user_email, $status_filter);

$data = ($type_filter === 'bookings') ? $user_bookings : $user_orders;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Your Orders</title>

<link rel="stylesheet" href="../styles.css">

</head>

<body class="your-order-page-body">

<!-- ✅ NAVBAR -->
<header class="navbar">
    <div class="navbar-brand-container">
        <img src="../img/logo.png" class="logo">
    </div>

    <div class="navbar-right">
        <ul class="navbar-menu">
            <li><a href="index.php">Home</a></li>
            <li><a href="products.php">Products</a></li>
            <li><a href="booking.php">Booking</a></li>
            <li><a href="cart.php">Cart</a></li>
            <li><a href="your_orders.php" class="active">Orders</a></li>
            <li><a href="aboutus.php">About</a></li>
        </ul>

        <div class="navbar-dropdown">
            <span class="navbar-user-btn">
                <?php echo htmlspecialchars($username); ?> ▼
            </span>

            <div class="navbar-dropdown-content">
                <a href="your_profile.php">My Profile</a>
                <a href="your_orders.php">Your Orders</a>
                <a href="../logout.php">Logout</a>
            </div>
        </div>
    </div>
</header>

<!-- ✅ MAIN CONTAINER -->
<div class="your-order-container">

    <!-- TITLE -->
    <h1 class="your-order-title">
        Your <?= ucfirst($type_filter) ?>
    </h1>

    <!-- TYPE FILTER -->
    <div class="your-order-filter-bar">
        <a href="?type=orders&status=all" class="<?= ($type_filter=='orders')?'active':'' ?>">Orders</a>
        <a href="?type=bookings&status=all" class="<?= ($type_filter=='bookings')?'active':'' ?>">Bookings</a>
    </div>

    <!-- STATUS FILTER -->
    <div class="your-order-filter-bar">
        <a href="?type=<?= $type_filter ?>&status=all" class="<?= ($status_filter=='all')?'active':'' ?>">All</a>
        <a href="?type=<?= $type_filter ?>&status=pending" class="<?= ($status_filter=='pending')?'active':'' ?>">Pending</a>
        <a href="?type=<?= $type_filter ?>&status=accepted" class="<?= ($status_filter=='accepted')?'active':'' ?>">Accepted</a>
        <a href="?type=<?= $type_filter ?>&status=rejected" class="<?= ($status_filter=='rejected')?'active':'' ?>">Rejected</a>
        <a href="?type=<?= $type_filter ?>&status=done" class="<?= ($status_filter=='done')?'active':'' ?>">Done</a>
    </div>

    <!-- GRID -->
    <div class="your-order-grid">

        <?php if(empty($data)): ?>
            <p class="your-order-empty">No records found.</p>
        <?php endif; ?>

        <?php foreach($data as $id => $item): ?>
        <?php $status = strtolower($item['status'] ?? 'pending'); ?>

        <div class="your-order-card">

            <div class="your-order-header">
                <strong><?= htmlspecialchars($item['full_name'] ?? 'User') ?></strong>

                <span class="your-order-badge your-order-<?= $status ?>">
                    <?= strtoupper($status) ?>
                </span>
            </div>

            <div class="your-order-total">
                ₱<?= number_format($item['total'] ?? 0, 2) ?>
            </div>

            <?php $products = $item['products'] ?? $item['items'] ?? []; ?>

            <?php if(!empty($products)): ?>
                <ul class="your-order-list">
                    <?php foreach($products as $p): ?>
                        <li>
                            <?= htmlspecialchars($p['name'] ?? '') ?>
                            <span>x<?= intval($p['qty'] ?? 1) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

        </div>

        <?php endforeach; ?>

    </div>

</div>

</body>
</html>