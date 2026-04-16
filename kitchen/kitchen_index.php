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
$orders = json_decode($orders_raw, true) ?? [];

// ================= GROUP =================
$walkin_orders = [];
$online_orders = [];

foreach($orders as $id => $order){

    // only accepted orders
    if(($order['status'] ?? '') !== 'accepted') continue;

    $kitchen_status = $order['kitchen_status'] ?? 'accepted';

    // 🚨 IMPORTANT FIX: hide completed orders
    if($kitchen_status === 'done') continue;

    // normalize bad data
    if(!in_array($kitchen_status, ['accepted','preparing','ready'])){
        $kitchen_status = 'accepted';
    }

    $order['_id'] = $id;
    $order['_kitchen_status'] = $kitchen_status;

    if(!empty($order['cashier'])){
        $walkin_orders[$id] = $order;
    } else {
        $online_orders[$id] = $order;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kitchen Queue</title>
<link rel="stylesheet" href="../styles.css">
</head>

<body>

<h1>🍽 Kitchen Queue</h1>
<nav class="navbar"> 
    <a href="kitchen_index.php" 
    class="navbar-brand">Kitchen Dashboard</a>
     <ul class="navbar-menu"> 
        <li><a href="kitchen_index.php">Queue</a></li> 
        <li><a href="kitchen_history.php">History</a></li>
         <li><a href="kitchen_logout.php">Logout</a></li> 
        </ul> 
    </nav>

<!-- ================= WALK-IN ================= -->
<h2>🧾 Walk-in Orders</h2>

<?php if(empty($walkin_orders)): ?>
    <p>No walk-in orders.</p>
<?php endif; ?>

<?php foreach($walkin_orders as $id => $order): ?>

<div class="card" style="border-left:5px solid orange; padding:10px; margin-bottom:10px;">

    <strong><?= htmlspecialchars($order['full_name'] ?? 'N/A') ?></strong>
    <br>
    <small>🧾 WALK-IN</small>
    <br>
    <small>📧 <?= htmlspecialchars($order['cashier'] ?? 'Cashier') ?></small>
    <br>

    <b>Status:</b> <?= strtoupper($order['_kitchen_status']) ?>
    <br><br>

    <?php foreach(($order['products'] ?? []) as $p): ?>
        • <?= htmlspecialchars($p['name']) ?> x <?= intval($p['qty']) ?><br>
    <?php endforeach; ?>

    <br>

    <form method="POST" action="kitchen_process.php">
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="order_id" value="<?= $id ?>">

        <?php if($order['_kitchen_status'] === 'accepted'): ?>
            <button name="status" value="preparing">Preparing</button>
        <?php endif; ?>

        <?php if($order['_kitchen_status'] === 'preparing'): ?>
            <button name="status" value="ready">Ready</button>
        <?php endif; ?>

        <?php if($order['_kitchen_status'] === 'ready'): ?>
            <button name="status" value="done">Done</button>
        <?php endif; ?>

    </form>

</div>

<?php endforeach; ?>

<hr>

<!-- ================= ONLINE ================= -->
<h2>👤 Online Orders</h2>

<?php if(empty($online_orders)): ?>
    <p>No online orders.</p>
<?php endif; ?>

<?php foreach($online_orders as $id => $order): ?>

<div class="card" style="border-left:5px solid blue; padding:10px; margin-bottom:10px;">

    <strong><?= htmlspecialchars($order['full_name'] ?? 'N/A') ?></strong>
    <br>
    <small>👤 ONLINE</small>
    <br>
    <small>📧 <?= htmlspecialchars($order['user_email'] ?? 'N/A') ?></small>
    <br>

    <b>Status:</b> <?= strtoupper($order['_kitchen_status']) ?>
    <br><br>

    <?php foreach(($order['products'] ?? []) as $p): ?>
        • <?= htmlspecialchars($p['name']) ?> x <?= intval($p['qty']) ?><br>
    <?php endforeach; ?>

    <br>

    <form method="POST" action="kitchen_process.php">
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="order_id" value="<?= $id ?>">

        <?php if($order['_kitchen_status'] === 'accepted'): ?>
            <button name="status" value="preparing">Preparing</button>
        <?php endif; ?>

        <?php if($order['_kitchen_status'] === 'preparing'): ?>
            <button name="status" value="ready">Ready</button>
        <?php endif; ?>

        <?php if($order['_kitchen_status'] === 'ready'): ?>
            <button name="status" value="done">Done</button>
        <?php endif; ?>

    </form>

</div>

<?php endforeach; ?>

</body>
</html>