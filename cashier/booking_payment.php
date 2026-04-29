<?php
session_start();

include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

if(!isset($_SESSION['cashier_email'])){
    header("Location: cashier_login.php");
    exit;
}

$rdb = new firebaseRDB($databaseURL);

$data = json_decode($rdb->retrieve("/bookings"), true);
$bookings = is_array($data) ? $data : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Booking Payment</title>

<link rel="stylesheet" href="../styles.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

</head>

<body class="cashier-booking-payment booking-payment">

<!-- ✅ FIXED NAVBAR (MATCHES ORDER PAGE) -->
<header class="navbar">

    <div class="navbar-brand-container">
        <img src="../img/logo.png" class="logo" alt="Logo">
    </div>

    <div class="navbar-right">

        <ul class="navbar-menu">
            <li><a href="cashier_index.php">Dashboard</a></li>
            <li><a href="view_orders.php">Orders</a></li>
            <li><a href="view_bookings.php" class="active">Bookings</a></li>
            <li><a href="cashier_orderHistory.php">History</a></li>
            <li><a href="cashier_logout.php">Logout</a></li>
        </ul>


    </div>

</header>


<!-- HEADER -->
<div class="booking-payment-header">
    <h1>Booking Payment</h1>
    <p>Verify GCash payments</p>
</div>


<!-- CONTENT -->
<div class="booking-payment-container">

<?php foreach($bookings as $id => $b): ?>

<?php
if(($b['payment_method'] ?? '') !== "gcash") continue;

$payment_status = $b['payment_status'] ?? 'pending_verification';
?>

<div class="booking-payment-card">

    <div class="booking-payment-top">
        <h3><?= htmlspecialchars($b['full_name'] ?? 'Unknown') ?></h3>
        <span class="booking-payment-badge accepted">ACCEPTED</span>
    </div>

    <div class="booking-payment-info">
        <p><b>Contact:</b> <?= htmlspecialchars($b['contact_number'] ?? '') ?></p>
        <p><b>Address:</b> <?= htmlspecialchars($b['address'] ?? '') ?></p>
        <p><b>Appointment:</b> <?= htmlspecialchars($b['appointment_time'] ?? '') ?></p>
    </div>

    <div class="booking-payment-status">
        <b>Payment:</b>
        <?php
            if($payment_status === "paid"){
                echo '<span class="booking-payment-badge paid">PAID</span>';
            } elseif($payment_status === "not_paid"){
                echo '<span class="booking-payment-badge notpaid">NOT PAID</span>';
            } else {
                echo '<span class="booking-payment-badge pending">PENDING</span>';
            }
        ?>
    </div>

    <!-- RECEIPT -->
    <?php if(!empty($b['gcash_receipt'])): ?>
        <?php $receipt = "../user/" . $b['gcash_receipt']; ?>
        <div class="booking-payment-receipt">
            <a href="<?= $receipt ?>" target="_blank">
                <img src="<?= $receipt ?>" alt="Receipt">
            </a>
        </div>
    <?php endif; ?>

    <!-- ITEMS -->
    <div class="booking-payment-items">

        <?php if(!empty($b['items']) && is_array($b['items'])): ?>

            <?php foreach($b['items'] as $item): ?>

                <div class="booking-payment-item">
                    <?= htmlspecialchars($item['name']) ?> x<?= intval($item['qty']) ?>
                    <span>₱<?= number_format($item['subtotal'], 2) ?></span>
                </div>

            <?php endforeach; ?>

            <div class="booking-payment-total">
                Total: ₱<?= number_format($b['booking_total'] ?? 0, 2) ?>
            </div>

        <?php else: ?>

            <div class="booking-payment-item">No items found</div>

        <?php endif; ?>

    </div>

    <!-- ACTION -->
    <div class="booking-payment-actions">

        <?php if($payment_status !== "paid"): ?>

            <form method="POST" action="cashier_process.php">
                <input type="hidden" name="action" value="mark_booking_paid">
                <input type="hidden" name="booking_id" value="<?= $id ?>">
                <button class="booking-payment-btn paid">
                    Mark Paid
                </button>
            </form>

        <?php else: ?>

            <span class="booking-payment-done">✔ Payment Completed</span>

        <?php endif; ?>

    </div>

</div>

<?php endforeach; ?>

</div>

</body>
</html>