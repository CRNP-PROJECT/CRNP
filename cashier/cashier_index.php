<?php
session_start();

include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

if(!isset($_SESSION['cashier_email'])){
    header("Location: cashier_login.php");
    exit;
}

$data = include(__DIR__ . "/cashier_index_process.php");

/* extract */
$today_order_sales = $data['today_order_sales'];
$today_booking_sales = $data['today_booking_sales'];
$today_total_sales = $data['today_total_sales'];


$pending_orders = $data['pending_orders'];
$today_bookings = $data['today_bookings'];
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
            <li><a href="scheduled_booking.php">Schedule Booking</a></li>
            <li><a href="cashier_orderHistory.php">History</a></li>
            <li><a href="cashier_logout.php">Logout</a></li>
        </ul>


    </div>

</header>

<div class="cashier-dashboard-wrapper">
    
    <div class="cashier-dashboard-header">
        <h1 class="cashier-dashboard-welcome-title">
            Welcome, Cashier
            <h4><p><?php echo htmlspecialchars($_SESSION['cashier_email'] ?? 'Cashier'); ?></p></h4>
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
                <span class="cashier-dashboard-btn-text-main">Verify Order Payment</span>
            </a>
            <a href="booking_payment.php" class="cashier-dashboard-btn-large cashier-dashboard-green">
                <span class="cashier-dashboard-btn-text-main">Verify Booking Payment</span>
            </a>

            <div class="cashier-dashboard-stat-row">
    <span>Order Sales Today</span>
    <span class="cashier-dashboard-stat-val">₱<?= number_format($today_order_sales, 2) ?></span>
        </div>

            

        <div class="cashier-dashboard-stat-row">
    <span>Booking Sales Today</span>
    <span class="cashier-dashboard-stat-val">₱<?= number_format($today_booking_sales, 2) ?></span>
        </div>

        <div class="cashier-dashboard-stat-row">
    <span>Total Sales Today</span>
    <span class="cashier-dashboard-stat-val">₱<?= number_format($today_total_sales, 2) ?></span>
    </div>

    
            </div>
        

        <!-- ================= CENTER ================= -->
<div class="cashier-dashboard-column cashier-dashboard-center">

    <!-- ================= PENDING BOOKINGS (TOP) ================= -->
    <div class="cashier-dashboard-tab-container">
        <button class="cashier-dashboard-tab active">
            TODAY'S BOOKINGS  (<?= count($latest_pending_bookings) ?>)
        </button>
    </div>

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

           <?php if(empty($latest_pending_bookings)): ?>
    <tr>
        <td colspan="3" style="text-align:center; padding:30px;">
            No pending bookings
        </td>
    </tr>
<?php else: ?>

    <?php foreach(array_slice($latest_pending_bookings, 0, 5) as $tb): ?>

    <tr>
        <td><?= date('h:i A', strtotime($tb['appointment_time'])) ?></td>
        <td><?= htmlspecialchars($tb['full_name']) ?></td>
        <td>
            <a href="view_bookings.php?booking_id=<?= $tb['id'] ?>" class="status pending">
                View
            </a>
        </td>
    </tr>

    <?php endforeach; ?>

<?php endif; ?>
            </tbody>
        </table>
    </div>

    <br>

    <!-- ================= PENDING ORDERS (BOTTOM) ================= -->
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
                <tr>
                    <td colspan="4" style="text-align:center; padding:30px;">
                        No pending orders
                    </td>
                </tr>
            <?php else: ?>

                <?php foreach(array_slice($pending_orders, 0, 5) as $po): ?>
                <tr>
                    <td>#<?= substr($po['id'], -4) ?></td>
                    <td><?= htmlspecialchars($po['full_name'] ?? 'Walk-in') ?></td>
                    <td>₱<?= number_format($po['total'] ?? 0, 2) ?></td>
                    <td>
                        <a href="view_orders.php" class="status pending">
                            View
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>

            <?php endif; ?>

            </tbody>
        </table>
    </div>

</div>

<div class="cashier-dashboard-column">

    <h2 class="cashier-dashboard-label">BOOKING RETURNING DATES</h2>

    <div class="cashier-dashboard-card-table">

        <table class="cashier-dashboard-data-table">

            <thead>
                <tr>
                    <th>Return Date</th>
                    <th>Time</th>
                    <th>Guest</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>

<?php if(empty($booking_returns)): ?>

<tr>
    <td colspan="4" style="text-align:center; padding:30px;">
        No returns scheduled
    </td>
</tr>

<?php else: ?>

<?php foreach($booking_returns as $r): ?>

<tr>

    <td>
        <?= date('M d, Y', strtotime($r['return_time'])) ?>
    </td>

    <td>
        <?= date('h:i A', strtotime($r['return_time'])) ?>
    </td>

    <td>
        <?= htmlspecialchars($r['full_name'] ?? '') ?>
    </td>

    <td>
        <a href="return_booking.php?date=<?= date('Y-m-d', strtotime($r['return_time'])) ?>"
           class="status accepted">
            View
        </a>
    </td>

</tr>

<?php endforeach; ?>

<?php endif; ?>

</tbody>
        </table>

    </div>
</div>
        

        <!-- RIGHT -->
        <div class="cashier-dashboard-column">

            <h2 class="cashier-dashboard-label">TODAY'S BOOKINGS SCHEDULED</h2>

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

                            <?php
$hasAccepted = false;


?>

<?php foreach($today_bookings as $tb): ?>

    <?php
    $status = strtolower($tb['status'] ?? 'pending');

    // SHOW ONLY ACCEPTED
    if($status != 'accepted'){
        continue;
    }

    $hasAccepted = true;
    ?>

    <tr>
        <td>
            <?= date('h:i A', strtotime($tb['appointment_time'])) ?>
        </td>

        <td>
            <?= htmlspecialchars($tb['full_name'] ?? '') ?>
        </td>

        <td>
            <a href="scheduled_booking.php" class="status accepted">
                View
            </a>
        </td>
    </tr>

<?php endforeach; ?>

<?php if(!$hasAccepted): ?>
<tr>
    <td colspan="3" style="text-align:center; padding:30px;">
        No accepted bookings today
    </td>
</tr>
<?php endif; ?>

                        <?php endif; ?>

                    </tbody>
                </table>
            </div>

        </div>

    </div>
</div>

</body>
</html>