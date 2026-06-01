<?php
session_start();

include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

if(!isset($_SESSION['cashier_email'])){
    header("Location: cashier_login.php");
    exit;
}

$rdb = new firebaseRDB($databaseURL);

$raw = $rdb->retrieve("/orders");
$data = json_decode($raw, true) ?? [];

/* FILTER */
$filter = $_GET['filter'] ?? 'all';

$history = [];

foreach($data as $order){

    if(!is_array($order)) continue;

    $orderStatus   = strtolower(trim($order['status'] ?? ''));
    $kitchenStatus = strtolower(trim($order['kitchen_status'] ?? ''));

    // normalize
    if(in_array($orderStatus, ['completed','finished'])){
        $orderStatus = 'done';
    }

    // FINAL STATUS LOGIC (kitchen overrides everything)
    if($kitchenStatus === 'done'){
        $finalStatus = 'done';
    } else {
        $finalStatus = $orderStatus;
    }

    // order type detection
    $type = strtolower($order['order_type'] ?? '');

    if($type === ''){
        if(isset($order['table_number']) || isset($order['cashier'])){
            $type = 'walkin';
        } else {
            $type = 'online';
        }
    }

    // store final status
    $order['final_status'] = $finalStatus;
    $order['order_type']   = $type;

    // only include valid statuses
    if(in_array($finalStatus, ['accepted','rejected','done'])){
        $history[] = $order;
    }
}

/* sort newest first */
usort($history, function($a, $b){
    return strtotime($b['cashier_action_time'] ?? $b['created_at'] ?? 0)
        <=> strtotime($a['cashier_action_time'] ?? $a['created_at'] ?? 0);
});
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../styles.css">

<title>Cashier History</title>
</head>

<body class="cashier-order-history">

<nav class="navbar">

    <div class="navbar-brand-container">
        <img src="../img/logo.png" class="logo" alt="Logo">
    </div>

    <ul class="navbar-menu">

        <li><a href="cashier_index.php">Dashboard</a></li>
        <li><a href="view_orders.php">Orders</a></li>
        <li><a href="cashier_orderHistory.php" class="active">Order History</a></li>
        <li><a href="payment_status.php">Payment Status</a></li>
        <li><a href="cashier_logout.php">Logout</a></li>

    </ul>
</nav>

<div class="history-header">
    <h1>Cashier History</h1>
    <p>All processed orders (walk-in & online)</p>
</div>

<!-- FILTER -->
<div class="cashier-history-booking-tabs">
    <a href="?filter=all" class="<?= $filter=='all'?'active':'' ?>">All</a>
    <a href="?filter=accepted" class="<?= $filter=='accepted'?'active':'' ?>">Accepted</a>
    <a href="?filter=done" class="<?= $filter=='done'?'active':'' ?>">Done</a>
    <a href="?filter=rejected" class="<?= $filter=='rejected'?'active':'' ?>">Rejected</a>
</div>

<div class="history-container">

<?php if(empty($history)): ?>
    <div class="history-empty">
        No history found.
    </div>
<?php endif; ?>

<?php foreach($history as $order): ?>

<?php
/* FILTER LOGIC */
$status = strtolower($order['final_status'] ?? '');

if ($filter == 'accepted' && $status != 'accepted') continue;
if ($filter == 'rejected' && $status != 'rejected') continue;
if ($filter == 'done' && $status != 'done') continue;

/* PH TIME FORMATTING */
$created_raw = $order['created_at'] ?? '';
$created = 'N/A';

if (!empty($created_raw)) {
    $dt1 = date_create($created_raw);
    if ($dt1) {
        $created = date_format($dt1, "M d, Y - h:i A");
    }
}

$appointment_raw = $order['appointment_time'] ?? '';
$appointment = 'Walk-in';

if (!empty($appointment_raw)) {
    $dt2 = date_create($appointment_raw);
    if ($dt2) {
        $appointment = date_format($dt2, "M d, Y - h:i A");
    }
}
?>

<div class="history-card">

    <div class="history-top">
        <h3>#<?= htmlspecialchars($order['order_id'] ?? '') ?></h3>

        <span class="type <?= $order['order_type'] ?>">
            <?= strtoupper($order['order_type']) ?>
        </span>
    </div>

    <p><b>Customer:</b> <?= htmlspecialchars($order['full_name'] ?? 'Walk-in') ?></p>
    <p><b>Total:</b> ₱<?= number_format($order['total'] ?? 0, 2) ?></p>

    <p><b>Status:</b>
        <span class="status <?= $status ?>">
            <?= strtoupper($status) ?>
        </span>
    </p>

    <p><b>Payment Status:</b>
        <?php
            $payment = $order['payment_status'] ?? '';

            if($payment === "paid"){
                echo '<span class="status paid">PAID</span>';
            }
            elseif($payment === "not_paid"){
                echo '<span class="status notpaid">NOT PAID</span>';
            }
            elseif($payment === "pending_verification"){
                echo '<span class="status pending">PENDING</span>';
            }
            elseif($payment === "no_payment_required"){
                echo '<span class="status pending">OVER THE COUNTER</span>';
            }
            else{
                echo '<span class="status pending">NO PAYMENT</span>';
            }
        ?>
    </p>

    <!-- ITEMS -->
    <div class="items">
        <b>Items:</b>
        <ul>
            <?php if(!empty($order['products'])): ?>
                <?php foreach($order['products'] as $item): ?>
                    <li>
                        <?= htmlspecialchars($item['name'] ?? '') ?> × <?= intval($item['qty'] ?? 0) ?>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>

    <p><b>Appointment:</b> <?= htmlspecialchars($appointment) ?></p>

    <p class="date">
        <i class="fa-regular fa-calendar"></i>
        <b>Ordered:</b> <?= htmlspecialchars($created) ?>
    </p>

</div>

<?php endforeach; ?>

</div>

</body>
</html>