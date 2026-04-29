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
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../styles.css">

<title>Cashier Orders</title>
</head>

<body class="cashier-order">

<!-- ✅ CLEAN CASHIER NAVBAR (FIXED) -->
<header class="navbar">

    <div class="navbar-brand-container">
        <img src="../img/logo.png" class="logo" alt="Logo">
    </div>

    <div class="navbar-right">

        <ul class="navbar-menu">
            <li><a href="cashier_index.php">Dashboard</a></li>
            <li><a href="create_order.php">Create Orders</a></li>
            <li><a href="view_orders.php" class="active">Orders</a></li>
            <li><a href="view_bookings.php">Bookings</a></li>
            <li><a href="cashier_orderHistory.php">History</a></li>
            <li><a href="cashier_logout.php">Logout</a></li>
        </ul>
    </div>

</header>

<!-- HEADER -->
<div class="cashier-order-header">
    <h1>Pending Orders</h1>
    <p>Manage all incoming orders</p>
</div>

<!-- TABLE CONTAINER -->
<div class="cashier-order-container">

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
            <button class="btn accept">✔</button>
        </form>

        <form method="POST" action="cashier_process.php" class="inline">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="order_id" value="<?= $id ?>">
            <input type="hidden" name="status" value="rejected">
            <button class="btn reject">✖</button>
        </form>

    </td>

    <td>
        <a href="cashier_view_order.php?id=<?= $id ?>" class="btn view">👁</a>
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