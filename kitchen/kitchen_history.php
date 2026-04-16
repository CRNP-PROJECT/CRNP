<?php
session_start();

include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

if(!isset($_SESSION['kitchen_email'])){
    header("Location: kitchen_login.php");
    exit;
}

$rdb = new firebaseRDB($databaseURL);

// ================= GET HISTORY =================
$history_raw = $rdb->retrieve("/kitchen_history");
$history = json_decode($history_raw, true) ?? [];
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../styles.css">
<title>Kitchen History</title>
</head>

<body>

<nav class="navbar">
    <a href="kitchen_index.php" class="navbar-brand">Kitchen Dashboard</a>

    <ul class="navbar-menu">
        <li><a href="kitchen_index.php">Queue</a></li>
        <li><a href="kitchen_history.php">History</a></li>
        <li><a href="kitchen_logout.php">Logout</a></li>
    </ul>
</nav>

<div class="container">

<h1 class="page-title">🍽 Completed Orders</h1>

<?php if(empty($history)): ?>

    <div class="card">
        <p class="text-muted">No completed orders yet.</p>
    </div>

<?php else: ?>

    <?php foreach($history as $id => $order): ?>

        <div class="card mb-2">

            <!-- ORDER HEADER -->
            <div class="mb-2">
                <strong>
                    <?= htmlspecialchars($order['full_name'] ?? $order['customer_name'] ?? 'N/A') ?>
                </strong>

                <br>

                <!-- EMAIL DISPLAY (IMPORTANT FIX) -->
                <small class="text-muted">
                    📧 <?= htmlspecialchars($order['email'] ?? 'Cashier') ?>
                </small>

                <br>

                <span class="badge badge-success">DONE</span>
            </div>

            <!-- ORDER INFO -->
            <p><b>Order ID:</b> <?= htmlspecialchars($order['order_id'] ?? $id) ?></p>
            <p><b>Total:</b> ₱<?= number_format($order['total'] ?? 0, 2) ?></p>

            <p><b>Completed:</b> <?= htmlspecialchars($order['completed_at'] ?? $order['created_at'] ?? '') ?></p>

            <hr>

            <!-- ITEMS -->
            <b>Items:</b>

            <?php
            $items = $order['products'] ?? $order['items'] ?? [];

            if(!empty($items)):
                foreach($items as $item):
            ?>
                <div style="padding:4px 0;">
                    <?= htmlspecialchars($item['name'] ?? 'Unknown') ?>
                    × <?= intval($item['qty'] ?? 0) ?>
                </div>
            <?php
                endforeach;
            else:
            ?>
                <p>No items found</p>
            <?php endif; ?>

        </div>

    <?php endforeach; ?>

<?php endif; ?>

</div>

</body>
</html>