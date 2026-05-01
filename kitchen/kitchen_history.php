<?php
session_start();

include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

if(!isset($_SESSION['kitchen_email'])){
    header("Location: kitchen_login.php");
    exit;
}

$rdb = new firebaseRDB($databaseURL);

// ================= GET ORDERS =================
$orders = json_decode($rdb->retrieve("/orders"), true) ?? [];

// ================= FILTER DONE =================
$history = [];

foreach($orders as $id => $order){

    if(strtolower($order['kitchen_status'] ?? '') !== 'done') continue;

    $order['_id'] = $id;
    $history[$id] = $order;
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../styles.css">

<title>Kitchen History</title>
</head>

<body class="kitchen-history-page">
<header class="navbar">
    <div class="navbar-brand-container">
        <img src="../img/logo.png" class="logo">
    </div>
    <div class="navbar-right">
        <ul class="navbar-menu">
            <li><a href="kitchen_index.php">Dashboard</a></li>
            <li><a href="kitchen_history.php" class="active">History</a></li>
            <li><a href="kitchen_logout.php">Logout</a></li>
        </ul>
    </div>
</header>
<!-- KEEP YOUR NAVBAR (no changes) -->

<div class="kitchen-history-container">

<h1 class="kitchen-history-title">Kitchen History</h1>
<p class="kitchen-history-subtitle">Completed kitchen orders</p>

<div id="history-container" class="kitchen-history-grid">

<?php if(empty($history)): ?>
    <div class="kitchen-history-empty">
        No completed orders yet.
    </div>
<?php else: ?>

<?php foreach($history as $order): ?>

<div class="kitchen-history-card">

    <!-- TOP -->
    <div class="kitchen-history-card-header">

        <div>
            <h3><?= htmlspecialchars($order['full_name'] ?? 'N/A') ?></h3>
            <p><?= strtoupper($order['order_type'] ?? 'ORDER') ?></p>
        </div>

        <span class="kitchen-history-badge done">DONE</span>
    </div>

    <!-- INFO -->
    <div class="kitchen-history-info">
        <p><strong>Order ID:</strong> <?= $order['_id'] ?></p>
        <p><strong>Total:</strong> ₱<?= number_format($order['total'] ?? 0, 2) ?></p>
        <p><strong>Completed:</strong> <?= $order['kitchen_action_time'] ?? 'N/A' ?></p>
    </div>

    <!-- ITEMS -->
    <div class="kitchen-history-items">
        <?php foreach(($order['products'] ?? []) as $item): ?>
            <div class="kitchen-history-item">
                <?= htmlspecialchars($item['name']) ?>
                <span>x<?= intval($item['qty']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>

</div>

<?php endforeach; ?>
<?php endif; ?>

</div>
</div>

<!-- LIVE UPDATE (UNCHANGED LOGIC) -->
<script>
let lastHTML = document.getElementById("history-container").innerHTML;

async function loadHistory() {
    try {
        const res = await fetch("fetch_kitchen_history.php");

        if(!res.ok) return;

        const data = await res.json();

        if(data && data.html){
            document.getElementById("history-container").innerHTML = data.html;
            lastHTML = data.html;
        }

    } catch (err) {
        document.getElementById("history-container").innerHTML = lastHTML;
    }
}

setInterval(loadHistory, 5000);
</script>

</body>
</html>