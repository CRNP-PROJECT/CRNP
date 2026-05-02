<?php
session_start();

include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

if(!isset($_SESSION['cashier_email'])){
    header("Location: cashier_login.php");
    exit;
}

$rdb = new firebaseRDB($databaseURL);

$data = json_decode($rdb->retrieve("/orders"), true);
$orders = is_array($data) ? $data : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Payment Verification</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="../styles.css">
</head>

<body class="payment-status">

<!-- NAVBAR -->
<header class="navbar">
    <div class="navbar-brand-container">
        <img src="../img/logo.png" class="logo" alt="Logo">
    </div>

    <div class="navbar-right">
        <ul class="navbar-menu">
            <li><a href="cashier_index.php">Dashboard</a></li>
            <li><a href="view_orders.php">Orders</a></li>
            <li><a href="cashier_orderHistory.php">History</a></li>
            <li><a href="cashier_logout.php">Logout</a></li>
        </ul>
    </div>
</header>

<!-- HEADER -->
<div class="payment-status-header">
    <h2>Payment Verification</h2>
    <p>GCash Transactions Only</p>
</div>

<!-- CONTAINER -->
<div class="container">

<?php foreach($orders as $id => $order): ?>

<?php
$status = strtolower($order['status'] ?? 'pending');
$payment_status = strtolower($order['payment_status'] ?? 'pending');
$payment_method = strtolower($order['payment_method'] ?? 'counter');

if($status !== "accepted") continue;
if($payment_method !== "gcash") continue;
?>

<!-- CARD -->
<div class="card">

    <!-- TOP -->
    <div class="payment-status-top">
        <h3><?= htmlspecialchars($order['full_name'] ?? 'Unknown'); ?></h3>
        <span class="status accepted">ACCEPTED</span>
    </div>

    <!-- INFO -->
    <div class="payment-status-info">
        <p><b>Total:</b> ₱<?= number_format($order['total'] ?? 0, 2); ?></p>
        <p><b>GCash Number:</b> <?= htmlspecialchars($order['gcash_number'] ?? 'N/A'); ?></p>
    </div>

    <!-- PAYMENT STATUS -->
    <div class="payment-status-line">
        <b>Payment:</b>
        <?php
            if($payment_status === "paid"){
                echo '<span class="status paid">PAID</span>';
            }
            elseif($payment_status === "not_paid"){
                echo '<span class="status notpaid">NOT PAID</span>';
            }
            else{
                echo '<span class="status pending">PENDING</span>';
            }
        ?>
    </div>

    <!-- RECEIPT -->
    <?php if(!empty($order['gcash_receipt'])): ?>
        <?php $receipt_url = "../user/" . $order['gcash_receipt']; ?>

        <div class="payment-status-receipt">
            <a href="<?= $receipt_url; ?>" target="_blank">
                <img src="<?= $receipt_url; ?>" alt="GCash Receipt">
            </a>
        </div>
    <?php endif; ?>

    <!-- ACTION -->
    <div class="payment-status-actions">

        <?php if($payment_status !== "paid"): ?>

            <form action="cashier_process.php" method="POST">
                <input type="hidden" name="order_id" value="<?= $id; ?>">

                <button class="btn paid-btn" name="action" value="mark_paid">
                    Mark Paid
                </button>

                <button class="btn reject-btn" name="action" value="mark_not_paid">
                    Not Paid
                </button>
            </form>

        <?php else: ?>

            <span class="payment-status-done">✔ Payment Completed</span>

        <?php endif; ?>

    </div>

</div>

<?php endforeach; ?>

</div>

</body>
</html>