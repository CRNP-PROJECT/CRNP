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
$status_filter = strtolower($_GET['status'] ?? 'all');

/* ================= FETCH DATA ================= */
$orders = json_decode($rdb->retrieve("/orders"), true) ?? [];
$bookings = json_decode($rdb->retrieve("/bookings"), true) ?? [];

/* ================= FINAL STATUS ENGINE ================= */
function getFinalStatus($item){

    $orderStatus = strtolower(trim($item['status'] ?? 'pending'));
    $kitchenStatus = strtolower(trim($item['kitchen_status'] ?? ''));

    if (in_array($orderStatus, ['completed', 'finished'])) {
        $orderStatus = 'done';
    }

    if (
        $orderStatus === 'done' ||
        $kitchenStatus === 'done' ||
        $kitchenStatus === 'completed'
    ) {
        return 'done';
    }

    if ($orderStatus === 'cancelled') {
    return 'cancelled';
}

    if ($orderStatus === 'rejected') {
        return 'rejected';
    }

    if (!empty($kitchenStatus)) {
        return $kitchenStatus;
    }
    

    return $orderStatus;
}

/* ================= FILTER FUNCTION ================= */
function filterData($data, $email, $status_filter){

    $result = [];

    foreach($data as $id => $item){

        if(!is_array($item)) continue;

        $item_email = $item['email'] ?? $item['user_email'] ?? '';

        if($item_email != $email){
            continue;
        }

        $status = getFinalStatus($item);

        if($status_filter != 'all' && $status != $status_filter){
            continue;
        }

        $item['_final_status'] = $status;
        $result[$id] = $item;
    }

    uasort($result, function($a,$b){
        return ($b['timestamp'] ?? 0) - ($a['timestamp'] ?? 0);
    });

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

<body class="navbar-body dashboard-body">

<header class="navbar">
    <div class="navbar-brand-container"><img src="../img/logo.png" class="logo"></div>
    <div class="navbar-right">
        <ul class="navbar-menu">
            <li><a href="index.php" class="active">Home</a></li>
            <li><a href="products.php">Products</a></li>
            <li><a href="booking.php">Booking</a></li>
            <li><a href="cart.php">Cart</a></li>
            <li><a href="aboutus.php">About</a></li>
        </ul>

        <form action="products.php" method="GET" class="search-box" style="position: relative;">
            <button type="submit" style="background:none; border:none; cursor:pointer; color:inherit;">
                <i class="fa-solid fa-magnifying-glass"></i>
            </button>
            <input type="text" name="search" placeholder="Search..." class="navbar-search" autocomplete="off">
            <div id="suggestion-box"></div>
        </form>

        <div class="navbar-dropdown">
            <span class="navbar-user-btn"><i class="fa-regular fa-user"></i> <?php echo htmlspecialchars($username); ?></span>
            <div class="navbar-dropdown-content">
                <a href="your_profile.php">My Profile</a>
                <a href="your_orders.php">Your Orders</a>
                <a href="../logout.php">Logout</a>
            </div>
        </div>
    </div>
</header>

<body class="your-order-page-body">

<div class="your-order-container">

<h1>YOUR <?= strtoupper($type_filter) ?></h1>

<div class="your-order-filter-bar">
    <a href="?type=orders&status=all">ORDERS</a>
    <a href="?type=bookings&status=all">BOOKINGS</a>
</div>

<div class="your-order-filter-bar">
    <a href="?type=<?= $type_filter ?>&status=all">All</a>
    <a href="?type=<?= $type_filter ?>&status=pending">Pending</a>
    <a href="?type=<?= $type_filter ?>&status=accepted">Accepted</a>
    <a href="?type=<?= $type_filter ?>&status=preparing">Preparing</a>
    <a href="?type=<?= $type_filter ?>&status=ready">Ready</a>
    <a href="?type=<?= $type_filter ?>&status=done">Done</a>
    <a href="?type=<?= $type_filter ?>&status=rejected">Rejected</a>
    <a href="?type=<?= $type_filter ?>&status=cancelled">Cancelled</a>
</div>

<div class="your-order-grid">

<?php foreach($data as $id => $item): ?>

<?php
$status = $item['_final_status'];
$kitchenStatus = strtolower($item['kitchen_status'] ?? '');

/* ================= TIME FIELDS ================= */
$createdAt = $item['created_at'] ?? '-';
$cancelledAt = $item['cancelled_at'] ?? '-';
$returnTime = $item['return_time'] ?? null;

$appt = $item['appointment_timestamp']
    ?? strtotime($item['appointment_time'] ?? '');

$total = $item['total'] ?? 0;
?>

<div class="your-order-card">

    <div class="your-order-header">
        <strong><?= htmlspecialchars($item['full_name'] ?? 'User') ?></strong>

        <span class="your-order-badge your-order-<?= $status ?>">
            <?= strtoupper($status) ?>
        </span>
    </div>

    <!-- ================= MAIN INFO ================= -->
    <div class="your-order-meta">

        <!-- CREATED AT -->
        <p><b>Created:</b> <?= htmlspecialchars($createdAt) ?></p>

        <!-- TOTAL / APPOINTMENT -->
        <?php if($type_filter == 'orders'): ?>
            <p><b>Total:</b> ₱<?= number_format($total, 2) ?></p>
        <?php else: ?>
            <p>
                <b>Return Time:</b>
                <?= $returnTime ? date('M d, Y h:i A', strtotime($returnTime)) : 'No return time' ?>
            </p>
        <?php endif; ?>

        <!-- APPOINTMENT (for bookings or fallback) -->
        <p>
            <b>Appointment:</b>
            <?= $appt ? date('M d, Y h:i A', $appt) : 'No schedule' ?>
        </p>

    </div>

    <!-- ================= PRODUCTS ================= -->
    <?php $products = $item['products'] ?? $item['items'] ?? []; ?>
    <?php foreach($products as $p): ?>
        <div><?= htmlspecialchars($p['name'] ?? '') ?> x<?= intval($p['qty'] ?? 1) ?></div>
    <?php endforeach; ?>

    <!-- ================= CANCEL INFO ================= -->
    <?php if($status === 'cancelled'): ?>
        <div class="cancel-info">
            <p>
                <strong>Reason:</strong>
                <?= htmlspecialchars($item['cancel_reason'] ?? 'No reason provided') ?>
            </p>

            <p>
                <strong>Cancelled At:</strong>
                <?= htmlspecialchars($cancelledAt) ?>
            </p>
        </div>
    <?php endif; ?>

    <!-- ================= CANCEL SECTION ================= -->
    <?php if(
        $status !== 'done' &&
        $status !== 'rejected' &&
        $status !== 'cancelled'
    ): ?>

        <?php if($type_filter == 'orders'): ?>

            <?php if($status === 'pending' || $status === 'accepted'): ?>
                <?php if(!in_array($kitchenStatus, ['preparing','ready'])): ?>
                    <form action="process.php" method="POST">
                        <input type="hidden" name="action" value="cancel_order">
                        <input type="hidden" name="order_id" value="<?= $id ?>">

                        <textarea name="cancel_reason" required></textarea>

                        <button type="submit"
                            onclick="return confirm('Are you sure you want to cancel this order?')">
                            Cancel Order
                        </button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>

        <?php else: ?>

            <?php
            $canCancelBooking =
                ($status === 'pending' || $status === 'accepted')
                && $appt > strtotime(date('Y-m-d'));
            ?>

            <?php if($canCancelBooking): ?>
                <form action="process.php" method="POST">
                    <input type="hidden" name="action" value="cancel_booking">
                    <input type="hidden" name="booking_id" value="<?= $id ?>">

                    <textarea name="cancel_reason" required placeholder="Reason for cancellation"></textarea>

                    <button type="submit"
                        onclick="return confirm('Are you sure you want to cancel this booking?')">
                        Cancel Booking
                    </button>
                </form>
            <?php endif; ?>

        <?php endif; ?>

    <?php endif; ?>

</div>

<?php endforeach; ?>

</div>
            </body>
            </html>