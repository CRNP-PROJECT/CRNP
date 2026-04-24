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
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>View Orders</title>

</head>
<body>

<nav>
    <a href="cashier_index.php">Dashboard</a> |
    <a href="view_orders.php">View Orders</a> |
    <a href="cashier_orderHistory.php">History</a>
</nav>

<h2>📦 Pending Orders Only</h2>

<table >
    <thead>
        <tr>
            <th>Order ID</th>
            <th>Type</th>
            <th>Customer</th>
            <th>Total</th>
            <th>Date</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
    </thead>

    <tbody>

    <?php
    $hasOrders = false;

    foreach($orders as $id => $order){

        if(!is_array($order)) continue;

        $status = strtolower($order['status'] ?? '');

        // 🔥 ONLY PENDING HERE
        if($status !== 'pending') continue;

        $hasOrders = true;

        // FIX TYPE SAFELY
        $type = strtolower($order['order_type'] ?? '');

        // if missing, detect walk-in
        if($type === ''){
            $type = isset($order['table_number']) ? 'walkin' : 'online';
        }

        $customer = $order['full_name'] ?? 'Unknown';
        $total = $order['total'] ?? 0;
        $date = $order['created_at'] ?? '';
    ?>

        <tr>

            <td><?= htmlspecialchars($id); ?></td>

            <td>
                <?php if($type === 'walkin'): ?>
                    <span style="color:blue;font-weight:bold;">WALK-IN</span>
                <?php else: ?>
                    <span style="color:green;font-weight:bold;">ONLINE</span>
                <?php endif; ?>
            </td>

            <td><?= htmlspecialchars($customer); ?></td>

            <td>₱<?= number_format($total, 2); ?></td>

            <td><?= htmlspecialchars($date ?: 'N/A'); ?></td>

            <td>
                <span class="badge badge-pending">PENDING</span>
            </td>

            <td>

                <form action="cashier_process.php" method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="order_id" value="<?= $id; ?>">
                    <input type="hidden" name="status" value="accepted">
                    <button>Accept</button>
                </form>

                <form action="cashier_process.php" method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="order_id" value="<?= $id; ?>">
                    <input type="hidden" name="status" value="rejected">
                    <button>Reject</button>
                </form>

            </td>

        </tr>

    <?php } ?>

    <?php if(!$hasOrders): ?>
        <tr>
            <td colspan="7">No pending orders</td>
        </tr>
    <?php endif; ?>

    </tbody>
</table>

</body>
</html>