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

/* ================= BUILD + SORT QUEUE ================= */
$walkin_orders = [];
$online_orders = [];
$all_orders = [];

foreach($orders as $id => $order){

    $status = strtolower($order['status'] ?? '');

    if($status === 'rejected') continue;

    $kitchen_status = strtolower($order['kitchen_status'] ?? 'accepted');

    if($kitchen_status === 'done') continue;

    if(!in_array($kitchen_status, ['accepted','preparing','ready'])){
        $kitchen_status = 'accepted';
    }

    $order['_id'] = $id;
    $order['_kitchen_status'] = $kitchen_status;
    $order['timestamp'] = $order['timestamp'] ?? time();

    $all_orders[] = $order;
}

/* ================= SORT OLDEST FIRST ================= */
usort($all_orders, function($a, $b) {
    return ($a['timestamp'] ?? 0) <=> ($b['timestamp'] ?? 0);
});

/* ================= CLASSIFY ================= */
foreach($all_orders as $order){

    $id = $order['_id'];
    $payment_method = strtolower($order['payment_method'] ?? '');

    if($payment_method === 'over the counter' || $payment_method === 'over_the_counter'){
        $walkin_orders[$id] = $order;
    } else {
        $online_orders[$id] = $order;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="../styles.css">

<title>Kitchen Dashboard</title>
</head>

<body class="kitchen-dashboard">

<header class="navbar">
    <div class="navbar-brand-container">
        <img src="../img/logo.png" class="logo">
    </div>
    <div class="navbar-right">
        <ul class="navbar-menu">
            <li><a href="kitchen_index.php" class="active">Dashboard</a></li>
            <li><a href="kitchen_history.php">History</a></li>
            <li><a href="kitchen_logout.php">Logout</a></li>
        </ul>
    </div>
</header>

<div class="container">

<h1 class="kitchen-title">Kitchen Orders</h1>

<div class="orders-grid">

    <!-- WALK-IN -->
    <div class="orders-column">
        <h2 class="orders-subtitle">Walk-in Orders</h2>

        <div id="walkin" class="orders-cards">

        <?php foreach($walkin_orders as $id => $order): ?>

        <div class="card walkin">

            <strong><?= htmlspecialchars($order['full_name'] ?? 'N/A') ?></strong>

            <small>
                <?= htmlspecialchars($order['time'] ?? date("h:i A", $order['timestamp'])) ?>
            </small>

            <small class="type">WALK-IN</small>

            <div class="status"><?= strtoupper($order['_kitchen_status']) ?></div>

            <div class="items">
                <?php foreach(($order['products'] ?? []) as $p): ?>
                    <div><?= htmlspecialchars($p['name']) ?> x <?= intval($p['qty']) ?></div>
                <?php endforeach; ?>
            </div>
<form method="POST" action="kitchen_process.php" class="status-buttons">

    <input type="hidden" name="action" value="update_status">
    <input type="hidden" name="order_id" value="<?= $id ?>">

    <button name="status" value="preparing"
        class="<?= $order['_kitchen_status'] == 'preparing' ? 'active' : '' ?>">
        Preparing
    </button>

    <button name="status" value="ready"
        class="<?= $order['_kitchen_status'] == 'ready' ? 'active' : '' ?>">
        Ready
    </button>

    <button name="status" value="done"
        class="<?= $order['_kitchen_status'] == 'done' ? 'active' : '' ?>">
        Done
    </button>

</form>

        </div>

        <?php endforeach; ?>

        </div>
    </div>

    <!-- ONLINE -->
    <div class="orders-column">
        <h2 class="orders-subtitle">Online Orders</h2>

        <div id="online" class="orders-cards">

        <?php foreach($online_orders as $id => $order): ?>

        <div class="card online">

            <strong><?= htmlspecialchars($order['full_name'] ?? 'N/A') ?></strong>

            <small>
                <?= htmlspecialchars($order['time'] ?? date("h:i A", $order['timestamp'])) ?>
            </small>

            <small class="type">ONLINE</small>

            <div class="status"><?= strtoupper($order['_kitchen_status']) ?></div>

            <div class="items">
                <?php foreach(($order['products'] ?? []) as $p): ?>
                    <div><?= htmlspecialchars($p['name']) ?> x <?= intval($p['qty']) ?></div>
                <?php endforeach; ?>
            </div>
<form method="POST" action="kitchen_process.php" class="status-buttons">

    <input type="hidden" name="action" value="update_status">
    <input type="hidden" name="order_id" value="<?= $id ?>">

    <button name="status" value="preparing"
        class="<?= $order['_kitchen_status'] == 'preparing' ? 'active' : '' ?>">
        Preparing
    </button>

    <button name="status" value="ready"
        class="<?= $order['_kitchen_status'] == 'ready' ? 'active' : '' ?>">
        Ready
    </button>

    <button name="status" value="done"
        class="<?= $order['_kitchen_status'] == 'done' ? 'active' : '' ?>">
        Done
    </button>

</form>
        </div>

        <?php endforeach; ?>

        </div>
    </div>

</div>
</div>

<!-- LIVE UPDATE -->
<script>
setInterval(() => {
    fetch("kitchen_fetch_orders.php")
    .then(res => res.json())
    .then(data => {
        document.getElementById("walkin").innerHTML = data.walkin;
        document.getElementById("online").innerHTML = data.online;
    });
}, 5000);
</script>

</body>
</html>