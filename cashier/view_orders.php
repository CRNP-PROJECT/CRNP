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
<link rel="stylesheet" href="../styles.css">
<title>View Orders</title>
</head>
<body>

<nav class="navbar">
    <a href="cashier_index.php" class="navbar-brand">Cashier Dashboard</a>
    <ul class="navbar-menu">
        <li><a href="view_orders.php">View Orders</a></li>
        <li><a href="cashier_orderHistory.php">History</a></li>
    </ul>
</nav>

<div class="container">

    <div class="page-header flex-between">
        <h1 class="page-title">Orders Status</h1>
        <a href="cashier_index.php" class="btn btn-secondary btn-sm">← Back</a>
    </div>

    <?php $hasOrders = false; ?>

    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>User Email</th>
                    <th>Customer Name</th>
                    <th>Total</th>
                    <th>Date & Time</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>

                <?php foreach($orders as $id => $order): ?>

                    <?php
                    // 🔥 ONLY SHOW PENDING
                    if(($order['status'] ?? '') !== 'pending') continue;

                    $hasOrders = true;

                    $status = $order['status'];
                    $email = $order['user_email'] ?? 'None';
                    $created_at = $order['created_at'] ?? '';
                    ?>

                    <tr>

                        <td><?= htmlspecialchars($id); ?></td>
                        <td><?= htmlspecialchars($email); ?></td>
                        <td><?= htmlspecialchars($order['full_name'] ?? ''); ?></td>
                        <td>₱<?= number_format($order['total'] ?? 0, 2); ?></td>

                        <td>
                            <?= !empty($created_at) 
                                ? date("M d, Y h:i A", strtotime($created_at)) 
                                : "N/A"; ?>
                        </td>

                        <td>
                            <span class="badge badge-<?= htmlspecialchars($status); ?>">
                                <?= strtoupper($status); ?>
                            </span>
                        </td>

                        <td>

                            <form style="display:inline;" action="cashier_process.php" method="POST">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="order_id" value="<?= $id; ?>">
                                <input type="hidden" name="status" value="accepted">
                                <button type="submit" class="btn btn-success btn-sm">Accept</button>
                            </form>

                            <form style="display:inline;" action="cashier_process.php" method="POST">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="order_id" value="<?= $id; ?>">
                                <input type="hidden" name="status" value="rejected">
                                <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                            </form>

                        </td>

                    </tr>

                <?php endforeach; ?>

                <?php if(!$hasOrders): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted">
                            No pending orders
                        </td>
                    </tr>
                <?php endif; ?>

            </tbody>

        </table>
    </div>

</div>

</body>
</html>