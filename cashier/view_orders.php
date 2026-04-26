<?php
session_start();

include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

header("Cache-Control: no-cache, must-revalidate");
date_default_timezone_set("Asia/Manila");

if(!isset($_SESSION['cashier_email'])){
    header("Location: cashier_login.php");
    exit;
}

$rdb = new firebaseRDB($databaseURL);

$orders_raw = $rdb->retrieve("/orders");
$orders = json_decode($orders_raw, true) ?? [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../styles.css">

<title>Cashier Orders</title>
</head>

<body class="cashier-view-orders">
    <nav class="navbar">

    <div class="navbar-brand-container">
        <img src="../img/logo.png" class="logo" alt="Logo">
    </div>

    <ul class="navbar-menu">

        <li>
            <a href="cashier_index.php">
                <i class="fa-solid fa-chart-line"></i> Dashboard
            </a>
        </li>

        <li>
            <a href="booking_history.php"></i> booking history
            </a>
        </li>

        <li>
            <a href="view_orders.php">
                <i class="fa-solid fa-receipt"></i> Orders
            </a>
        </li>

        <li>
            <a href="booking_payment.php" class="active">
                <i class="fa-solid fa-calendar-days"></i> Booking status
            </a>
        </li>

        <li>
            <a href="cashier_logout.php">
                <i class="fa-solid fa-right-from-bracket"></i> Logout
            </a>
        </li>

    </ul>
</nav>

<!-- NAVBAR -->
<nav class="navbar">

    <div class="navbar-brand-container">
        <img src="../img/logo.png" class="logo" alt="Logo">
    </div>

    <ul class="navbar-menu">

        <li><a href="payment_status.php"><i class="fa-solid fa-money-check"></i> Payment Status</a></li>
        <li class="active"><a href="view_orders.php"><i class="fa-solid fa-receipt"></i> Orders</a></li>
        <li><a href="cashier_orderHistory.php"><i class="fa-solid fa-clock-rotate-left"></i> History</a></li>
        <li><a href="cashier_logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>

    </ul>
</nav>

<div class="history-header">
    <h1><i class="fa-solid fa-box"></i> Pending Orders</h1>
    <p>Manage all incoming orders</p>
</div>

<div class="orders-container">

<table class="orders-table">

<thead>
<tr>
    <th>Order ID</th>
    <th>Type</th>
    <th>Customer</th>
    <th>Total</th>
    <th>Date</th>
    <th>Status</th>
    <th>Action</th>
    <th>View</th>
</tr>
</thead>

<tbody>

<?php
$hasOrders = false;

foreach($orders as $id => $order){

    if(!is_array($order)) continue;

    $status = strtolower($order['status'] ?? '');
    if($status !== 'pending') continue;

    $hasOrders = true;

    $type = strtolower($order['order_type'] ?? '');
    if($type === ''){
        $type = isset($order['table_number']) ? 'walkin' : 'online';
    }

    $customer = $order['full_name'] ?? 'Unknown';
    $total = $order['total'] ?? 0;
    $date = $order['created_at'] ?? '';
?>

<tr>

    <td><?= htmlspecialchars($id) ?></td>

    <td>
        <span class="<?= $type ?>">
            <?= strtoupper($type) ?>
        </span>
    </td>

    <td><?= htmlspecialchars($customer) ?></td>

    <td>₱<?= number_format($total, 2) ?></td>

    <td><?= htmlspecialchars($date ?: 'N/A') ?></td>

    <td><span class="badge pending">PENDING</span></td>

    <td>

        <form method="POST" action="cashier_process.php" class="inline">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="order_id" value="<?= $id ?>">
            <input type="hidden" name="status" value="accepted">

            <button class="btn accept">
                <i class="fa-solid fa-check"></i>
            </button>
        </form>

        <form method="POST" action="cashier_process.php" class="inline">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="order_id" value="<?= $id ?>">
            <input type="hidden" name="status" value="rejected">

            <button class="btn reject">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </form>

    </td>

    <td>
        <a href="cashier_view_order.php?id=<?= $id ?>" class="btn view">
            <i class="fa-solid fa-eye"></i>
        </a>
    </td>

</tr>

<?php } ?>

<?php if(!$hasOrders): ?>
<tr>
    <td colspan="8" class="empty">No pending orders</td>
</tr>
<?php endif; ?>

</tbody>
</table>

</div>

</body>
</html>