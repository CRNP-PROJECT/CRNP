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
<title>Booking Payment Verification</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<style>
body { font-family: Arial; background:#f5f5f5; }

.container { padding:20px; }

.card {
    background:#fff;
    padding:15px;
    margin-bottom:15px;
    border-radius:10px;
    box-shadow:0 2px 6px rgba(0,0,0,0.1);
}

.status {
    padding:5px 10px;
    border-radius:5px;
    color:#fff;
    font-size:12px;
}

.accepted { background:green; }
.pending { background:orange; }
.paid { background:blue; }
.notpaid { background:red; }

.btn {
    padding:8px 12px;
    border:none;
    cursor:pointer;
    margin-right:5px;
    border-radius:5px;
    color:#fff;
}

.paid-btn { background:green; }
.reject-btn { background:red; }

img {
    max-width:200px;
    border-radius:8px;
}

.item {
    padding:5px 0;
    border-bottom:1px solid #eee;
}
</style>
</head>

<body>

<!-- NAV -->
<nav class="navbar">

    <div class="navbar-brand-container">
        <img src="../img/logo.png" class="logo" alt="Logo">
    </div>

    <ul class="navbar-menu">

        <li><a href="cashier_index.php"><i class="fa-solid fa-chart-line"></i> Dashboard</a></li>

        <li><a href="booking_history.php">Booking History</a></li>

        <li><a href="view_orders.php"><i class="fa-solid fa-receipt"></i> Orders</a></li>

        <li><a href="booking_payment.php" class="active"><i class="fa-solid fa-calendar-days"></i> Booking Status</a></li>

        <li><a href="cashier_logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>

    </ul>
</nav>

<div class="container">

<h2>Booking Payment Verification (GCash Only)</h2>

<?php foreach($bookings as $id => $b): ?>

<?php
if(($b['payment_method'] ?? '') !== "gcash") continue;

$status = strtolower($b['status'] ?? 'pending');
$payment_status = $b['payment_status'] ?? 'pending_verification';
?>

<div class="card">

    <h3><?= htmlspecialchars($b['full_name'] ?? 'Unknown') ?></h3>

    <p><b>Contact:</b> <?= htmlspecialchars($b['contact_number'] ?? '') ?></p>
    <p><b>Address:</b> <?= htmlspecialchars($b['address'] ?? '') ?></p>

    <p><b>Appointment:</b> <?= htmlspecialchars($b['appointment_time'] ?? '') ?></p>

    <p><span class="status accepted">ACCEPTED</span></p>

    <p><b>Payment Status:</b>
        <?php
            if($payment_status === "paid"){
                echo '<span class="status paid">PAID</span>';
            } elseif($payment_status === "not_paid"){
                echo '<span class="status notpaid">NOT PAID</span>';
            } else {
                echo '<span class="status pending">PENDING</span>';
            }
        ?>
    </p>

    <p><b>GCash Number:</b> <?= htmlspecialchars($b['gcash_number'] ?? 'N/A') ?></p>

    <!-- RECEIPT -->
    <?php if(!empty($b['gcash_receipt'])): ?>
        <?php $receipt = "../user/" . $b['gcash_receipt']; ?>

        <p><b>Receipt:</b></p>
        <a href="<?= $receipt ?>" target="_blank">
            <img src="<?= $receipt ?>" alt="Receipt">
        </a>
    <?php else: ?>
        <p><b>Receipt:</b> None</p>
    <?php endif; ?>

    <!-- ✅ UPDATED BOOKING ITEMS (NEW STRUCTURE) -->
    <h4>Booking Details</h4>

    <?php if(!empty($b['items']) && is_array($b['items'])): ?>

        <?php foreach($b['items'] as $item): ?>

            <div class="item">
                📦 <?= htmlspecialchars($item['name']) ?>
                (x<?= intval($item['qty']) ?>)
                - ₱<?= number_format($item['subtotal'], 2) ?>
            </div>

        <?php endforeach; ?>

        <div class="item" style="font-weight:bold;">
            💰 Total: ₱<?= number_format($b['booking_total'] ?? 0, 2) ?>
        </div>

    <?php else: ?>

        <div class="item">No items found</div>

    <?php endif; ?>

    <!-- ACTION -->
    <?php if($payment_status !== "paid"): ?>
        <form method="POST" action="cashier_process.php">

            <input type="hidden" name="action" value="mark_booking_paid">
            <input type="hidden" name="booking_id" value="<?= $id ?>">

            <button class="btn paid-btn">Mark Paid</button>
        </form>
    <?php else: ?>
        <p style="color:green;font-weight:bold;">✔ Payment Completed</p>
    <?php endif; ?>

</div>

<?php endforeach; ?>

</div>

</body>
</html>