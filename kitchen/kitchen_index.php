<?php
session_start();

include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

if(!isset($_SESSION['kitchen_email'])){
    header("Location: kitchen_login.php");
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
<title>Kitchen Dashboard</title>
</head>
<body>

<nav class="navbar">
    <a href="kitchen_index.php" class="navbar-brand">Kitchen Dashboard</a>
    <ul class="navbar-menu">
        <li><a href="kitchen_logout.php">Logout</a></li>
    </ul>
</nav>

<div class="container">
    <div class="page-header">
        <h1 class="page-title">Kitchen Orders</h1>
        <p class="page-subtitle">
            Welcome: <?php echo htmlspecialchars($_SESSION['kitchen_email']); ?>
        </p>
    </div>

    <?php if(empty($orders)): ?>
        <div class="card">
            <p class="text-center text-muted">No orders found.</p>
        </div>
    <?php else: ?>

        <?php 
        $hasAcceptedOrders = false;

        foreach($orders as $id => $order):

            if(($order['status'] ?? '') === 'accepted'):
                $hasAcceptedOrders = true;

                $kitchen_status = $order['kitchen_status'] ?? 'pending';
        ?>

        <div class="card mb-2">

            <div class="flex-between mb-2">
                <div>
                    <strong><?php echo htmlspecialchars($order['full_name']); ?></strong>

                    <!-- STATUS BADGE -->
                    <span class="badge badge-<?php echo htmlspecialchars($kitchen_status); ?> ml-1">
                        <?php echo strtoupper($kitchen_status); ?>
                    </span>
                </div>
            </div>

            <div class="mb-2">
                <?php foreach($order['products'] as $p): ?>
                    <div style="padding: 4px 0; border-bottom: 1px dashed var(--border);">
                        <span><?php echo htmlspecialchars($p['name']); ?></span>
                        <span class="text-muted"> × <?php echo intval($p['qty']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- BUTTONS -->
            <?php if($kitchen_status !== 'done'): ?>
            <form method="POST" action="kitchen_process.php" class="flex gap-1">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="order_id" value="<?php echo $id; ?>">

                <button type="submit" name="status" value="preparing" class="btn btn-secondary btn-sm">
                    Preparing
                </button>

                <button type="submit" name="status" value="ready" class="btn btn-primary btn-sm">
                    Ready
                </button>

                <button type="submit" name="status" value="done" class="btn btn-success btn-sm">
                    Done
                </button>
            </form>
            <?php else: ?>
                <p class="text-success"><strong>Order Completed</strong></p>
            <?php endif; ?>

        </div>

        <?php 
            endif;
        endforeach;

        if(!$hasAcceptedOrders):
        ?>
        <div class="card">
            <p class="text-center text-muted">No accepted orders to display.</p>
        </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

</body>
</html>