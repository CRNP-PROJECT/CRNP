<?php
session_start();

include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

if(!isset($_SESSION['cashier_email'])){
    header("Location: cashier_login.php");
    exit;
}

// ✅ IMPORTANT: set timezone (fixes wrong "today")
date_default_timezone_set('Asia/Manila');

$rdb = new firebaseRDB($databaseURL);

// --- FETCH DATA ---
$orders_raw = $rdb->retrieve("/orders");
$orders = json_decode($orders_raw, true) ?? [];

$bookings_raw = $rdb->retrieve("/bookings");
$bookings = json_decode($bookings_raw, true) ?? [];

// --- DATE SETUP ---
$today = date('Y-m-d');
$current_month = date('Y-m');

// --- STATS ---
$today_sales = 0;
$monthly_sales = 0;
$order_count = 0;
$pending_orders = [];
$today_bookings = [];

/* ================= ORDERS ================= */
foreach($orders as $id => $order){

    if(empty($order['created_at'])) continue;

    $created = strtotime($order['created_at']);
    if(!$created) continue; // ✅ prevent 1970 bug

    $order_date = date('Y-m-d', $created);
    $order_month = date('Y-m', $created);

    $status = strtolower($order['status'] ?? '');
    $payment_status = strtolower($order['payment_status'] ?? '');

    $total = floatval($order['total'] ?? 0);

    // ✅ COUNT ONLY PAID
    if($payment_status === 'paid'){

        if($order_date === $today){
            $today_sales += $total;
            $order_count++;
        }

        if($order_month === $current_month){
            $monthly_sales += $total;
        }
    }

    // pending list
    if($status === 'pending'){
        $order['id'] = $id;
        $pending_orders[] = $order;
    }
}

/* ================= BOOKINGS ================= */
foreach($bookings as $id => $b){

    if(empty($b['appointment_time'])) continue;

    $appt_time = strtotime($b['appointment_time']);
    if(!$appt_time) continue; // ✅ safety

    $booking_date = date('Y-m-d', $appt_time);
    $booking_month = date('Y-m', $appt_time);

    $status = strtolower($b['status'] ?? '');
    $payment_status = strtolower($b['payment_status'] ?? '');

    $total = floatval($b['booking_total'] ?? 0);

    // ✅ INCLUDE BOOKINGS IN SALES
    if($payment_status === 'paid'){

        if($booking_date === $today){
            $today_sales += $total;
            $order_count++; // ✅ include in avg
        }

        if($booking_month === $current_month){
            $monthly_sales += $total;
        }
    }

    // today's bookings list
    if($booking_date === $today && $status === 'pending'){
        $today_bookings[] = $b;
    }
}

/* ================= SORT BOOKINGS ================= */
usort($today_bookings, function($a, $b){
    return strtotime($a['appointment_time']) - strtotime($b['appointment_time']);
});

$avg_order = ($order_count > 0) ? ($today_sales / $order_count) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../styles.css">
<title>Cashier Dashboard</title>
</head>

<body class="cashier-dashboard">

<header class="navbar">

    <div class="navbar-brand-container">
        <img src="../img/logo.png" class="logo" alt="Logo">
    </div>

    <div class="navbar-right">

        <ul class="navbar-menu">
            <li><a href="cashier_index.php" class="active">Dashboard</a></li>
            <li><a href="create_order.php">Create Orders</a></li>
            <li><a href="view_orders.php">Orders</a></li>
            <li><a href="view_bookings.php">Bookings</a></li>
            <li><a href="cashier_orderHistory.php">History</a></li>
            <li><a href="cashier_logout.php">Logout</a></li>
        </ul>


    </div>

</header>

<div class="cashier-dashboard-wrapper">
    
    <div class="cashier-dashboard-header">
        <h1 class="cashier-dashboard-welcome-title">
            Welcome, Cashier
        </h1>

        <!-- ✅ ADDED TIME -->
        <p class="cashier-dashboard-welcome-sub">
            Date: <?= date('F j, Y - h:i A') ?>
        </p>
    </div>

    <div class="cashier-dashboard-main-layout">
        
        <!-- LEFT -->
        <div class="cashier-dashboard-column">

            <h2 class="cashier-dashboard-label">Quick Actions</h2>

            <a href="create_order.php" class="cashier-dashboard-btn-large cashier-dashboard-green">
                <span class="cashier-dashboard-btn-text-main">CREATE ORDER</span>
                <span class="cashier-dashboard-btn-text-sub">Walk-in / Online</span>
            </a>

            <a href="payment_status.php" class="cashier-dashboard-btn-large cashier-dashboard-blue">
                <span class="cashier-dashboard-btn-text-main">VERIFY PAYMENTS</span>
            </a>

            <div class="cashier-dashboard-card-plain">
                <h2 class="cashier-dashboard-label">Revenue Snapshot</h2>

                <div class="cashier-dashboard-stat-row">
                    <span>Today's Sales</span>
                    <span class="cashier-dashboard-stat-val">₱<?= number_format($today_sales, 2) ?></span>
                </div>

                <div class="cashier-dashboard-stat-row">
                    <span>Monthly Sales</span>
                    <span class="cashier-dashboard-stat-val">₱<?= number_format($monthly_sales, 2) ?></span>
                </div>

                <div class="cashier-dashboard-stat-row">
                    <span>Avg Order</span>
                    <span class="cashier-dashboard-stat-val">₱<?= number_format($avg_order, 2) ?></span>
                </div>

            </div>
        </div>

        <!-- CENTER -->
        <div class="cashier-dashboard-column cashier-dashboard-center">

            <div class="cashier-dashboard-tab-container">
                <button class="cashier-dashboard-tab active">
                    PENDING ORDERS (<?= count($pending_orders) ?>)
                </button>
            </div>

            <div class="cashier-dashboard-card-table">
                <table class="cashier-dashboard-data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Total</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>

                        <?php if(empty($pending_orders)): ?>
                            <tr><td colspan="4" style="text-align:center; padding:30px;">No pending orders</td></tr>
                        <?php else: ?>

                            <?php foreach(array_slice($pending_orders, 0, 5) as $po): ?>
                            <tr>
                                <td>#<?= substr($po['id'], -4) ?></td>
                                <td><?= htmlspecialchars($po['full_name'] ?? 'Walk-in') ?></td>
                                <td>₱<?= number_format($po['total'] ?? 0, 2) ?></td>
                                <td><a href="view_orders.php" class="status pending">View</a></td>
                            </tr>
                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>
                </table>
            </div>

        </div>

        <!-- RIGHT -->
        <div class="cashier-dashboard-column">

            <h2 class="cashier-dashboard-label">TODAY'S BOOKINGS</h2>

            <div class="cashier-dashboard-card-table">
                <table class="cashier-dashboard-data-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Guest</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>

                        <?php if(empty($today_bookings)): ?>
                            <tr><td colspan="3" style="text-align:center; padding:30px;">None for today</td></tr>
                        <?php else: ?>

                            <?php foreach($today_bookings as $tb): ?>
                            <tr>
                                <td><?= date('h:i A', strtotime($tb['appointment_time'])) ?></td>
                                <td><?= htmlspecialchars($tb['full_name'] ?? '') ?></td>
                                <td><span class="status pending">PENDING</span></td>
                            </tr>
                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>
                </table>
            </div>

        </div>

    </div>
</div>

</body>
</html>