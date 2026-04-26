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

<style>
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
    cursor:pointer;
    border-radius:8px;
}
</style>
</head>

<body>

<nav class="navbar">
    <div class="navbar-brand-container">
        <img src="../img/logo.png" class="logo">
    </div>

    <ul class="navbar-menu">
        <li><a href="cashier_index.php">Home</a></li>
        <li><a href="view_orders.php">view orders</a></li>
        <li><a href="cashier_orderHistory.php">order history</a></li>
    </ul>
</nav>

<div class="container">

<h2>Payment Verification (GCash Only)</h2>

<?php foreach($orders as $id => $order): ?>

<?php
$status = strtolower($order['status'] ?? 'pending');
$payment_status = strtolower($order['payment_status'] ?? 'pending');
$payment_method = strtolower($order['payment_method'] ?? 'counter');

// ✅ ONLY ACCEPTED + GCASH
if($status !== "accepted") continue;
if($payment_method !== "gcash") continue;
?>

<div class="card">

    <h3><?php echo htmlspecialchars($order['full_name'] ?? 'Unknown'); ?></h3>

    <p><b>Total:</b> ₱<?php echo number_format($order['total'] ?? 0); ?></p>

    <p>
        <span class="status accepted">ACCEPTED</span>
    </p>

    <!-- PAYMENT STATUS -->
    <p>
        <b>Payment Status:</b>
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
    </p>

    <p>
        <b>GCash Number:</b>
        <?php echo htmlspecialchars($order['gcash_number'] ?? 'N/A'); ?>
    </p>

    <!-- RECEIPT -->
    <?php if(!empty($order['gcash_receipt'])): ?>

        <?php $receipt_url = "../user/" . $order['gcash_receipt']; ?>

        <p><b>Receipt:</b></p>

        <a href="<?php echo $receipt_url; ?>" target="_blank">
            <img src="<?php echo $receipt_url; ?>" alt="GCash Receipt">
        </a>

    <?php else: ?>
        <p><b>Receipt:</b> None</p>
    <?php endif; ?>

    <!-- BUTTONS HIDE IF PAID -->
    <?php if($payment_status !== "paid"): ?>

        <form action="cashier_process.php" method="POST">

            <input type="hidden" name="order_id" value="<?php echo $id; ?>">

            <button class="btn paid-btn" name="action" value="mark_paid">
                Mark Paid
            </button>

            <button class="btn reject-btn" name="action" value="mark_not_paid">
                Not Paid
            </button>

        </form>

    <?php else: ?>

        <p style="color:green; font-weight:bold;">✔ Payment Completed</p>

    <?php endif; ?>

</div>

<?php endforeach; ?>

</div>

</body>
</html>