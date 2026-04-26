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

    $status = strtolower($order['status'] ?? '');
    $payment_status = strtolower($order['payment_status'] ?? '');
    $payment_method = strtolower($order['payment_method'] ?? '');

    // ❌ only reject rejected orders
    if($status === 'rejected') continue;

    // kitchen status
    $kitchen_status = strtolower($order['kitchen_status'] ?? 'accepted');

    if($kitchen_status === 'done') continue;

    if(!in_array($kitchen_status, ['accepted','preparing','ready'])){
        $kitchen_status = 'accepted';
    }

    $order['_id'] = $id;
    $order['_kitchen_status'] = $kitchen_status;

    // classify
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

<nav class="navbar">
    <div class="navbar-brand-container">
        <img src="../img/logo.png" class="logo">
    </div>

    <ul class="navbar-menu">
        <li><a href="kitchen_index.php">Queue</a></li>
        <li><a href="kitchen_history.php">History</a></li>
        <li><a href="kitchen_logout.php">Logout</a></li>
    </ul>
</nav>

<div class="container">

<h1>🍳 Kitchen Orders</h1>

<!-- 🔥 IMPORTANT: LIVE UPDATE TARGETS -->
<div class="orders-grid">

    <h2>🧾 Walk-in Orders</h2>
    <div id="walkin">

        <?php foreach($walkin_orders as $id => $order): ?>

        <div class="card" style="border-left:5px solid orange; margin-bottom:10px; padding:10px;">

            <strong><?= htmlspecialchars($order['full_name'] ?? 'N/A') ?></strong>
            <br>
            <small>🧾 WALK-IN</small>

            <br><br>

            <b>Status:</b> <?= strtoupper($order['_kitchen_status']) ?>
            <br><br>

            <?php foreach(($order['products'] ?? []) as $p): ?>
                • <?= htmlspecialchars($p['name']) ?> x <?= intval($p['qty']) ?><br>
            <?php endforeach; ?>

        </div>

        <?php endforeach; ?>

    </div>

    <hr>

    <h2>👤 Online Orders</h2>
    <div id="online">

        <?php foreach($online_orders as $id => $order): ?>

        <div class="card" style="border-left:5px solid blue; margin-bottom:10px; padding:10px;">

            <strong><?= htmlspecialchars($order['full_name'] ?? 'N/A') ?></strong>
            <br>
            <small>👤 ONLINE</small>

            <br><br>

            <b>Status:</b> <?= strtoupper($order['_kitchen_status']) ?>
            <br><br>

            <?php foreach(($order['products'] ?? []) as $p): ?>
                • <?= htmlspecialchars($p['name']) ?> x <?= intval($p['qty']) ?><br>
            <?php endforeach; ?>

        </div>

        <?php endforeach; ?>

    </div>

</div>

</div>

<!-- ================= SAFE LIVE UPDATE ================= -->
<script>
let lastWalkin = "";
let lastOnline = "";

async function loadKitchenOrders() {
    try {
        const res = await fetch("kitchen_fetch_orders.php");

        if(!res.ok) return;

        const data = await res.json();

        const walkin = document.getElementById("walkin");
        const online = document.getElementById("online");

        if(walkin && data.walkin !== undefined){
            walkin.innerHTML = data.walkin;
            lastWalkin = data.walkin;
        }

        if(online && data.online !== undefined){
            online.innerHTML = data.online;
            lastOnline = data.online;
        }

    } catch (err) {
        console.log("Refresh error:", err);

        // prevent vanishing
        if(document.getElementById("walkin")){
            document.getElementById("walkin").innerHTML = lastWalkin;
        }

        if(document.getElementById("online")){
            document.getElementById("online").innerHTML = lastOnline;
        }
    }
}

setInterval(loadKitchenOrders, 5000);
</script>

</body>
</html>