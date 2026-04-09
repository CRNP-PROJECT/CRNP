<?php
include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

header("Cache-Control: no-cache, must-revalidate");

if(!isset($_SESSION['cashier_email'])){
    header("Location: cashier_login.php");
    exit;
}

$rdb = new firebaseRDB($databaseURL);

$orders_raw = $rdb->retrieve("/orders");
$orders = json_decode($orders_raw, true);
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
        <li><a href="view_bookings.php">View Bookings</a></li>
        <li><a href="cashier_logout.php">Logout</a></li>
    </ul>
</nav>

<div class="container">
    <div class="page-header flex-between">
        <div>
            <h1 class="page-title">Orders Status</h1>
        </div>
        <a href="cashier_index.php" class="btn btn-secondary btn-sm">← Back</a>
    </div>

    <?php if(empty($orders)): ?>
        <div class="card">
            <p class="text-center text-muted">No orders yet.</p>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>User Email</th>
                        <th>Customer Name</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($orders as $id => $order): 
                        $status = $order['status'] ?? 'pending';
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($id); ?></td>
                        <td><?php echo htmlspecialchars($order['user_email'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($order['full_name'] ?? ''); ?></td>
                        <td>₱<?php echo number_format($order['total'] ?? 0, 2); ?></td>
                        <td>
                            <span class="badge badge-<?php echo htmlspecialchars($status); ?>">
                                <?php echo strtoupper($status); ?>
                            </span>
                        </td>
                        <td>
                            <?php if($status === 'pending'): ?>
                                <form style="display:inline;" action="cashier_process.php" method="POST">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="order_id" value="<?php echo $id; ?>">
                                    <input type="hidden" name="status" value="accepted">
                                    <button type="submit" class="btn btn-success btn-sm">Accept</button>
                                </form>
                                <form style="display:inline;" action="cashier_process.php" method="POST">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="order_id" value="<?php echo $id; ?>">
                                    <input type="hidden" name="status" value="rejected">
                                    <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted">Processed</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
