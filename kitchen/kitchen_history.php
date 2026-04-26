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
$history_raw = $rdb->retrieve("/orders");
$orders = json_decode($history_raw, true) ?? [];

// ================= FILTER DONE ONLY =================
$history = [];

foreach($orders as $id => $order){

    if(strtolower($order['kitchen_status'] ?? '') !== 'done') continue;

    $history[$id] = $order;
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="../styles.css">
<title>Kitchen History</title>
</head>

<body class="kitchen-history-page">

<nav class="navbar">
    <ul class="navbar-menu">
        <li><a href="kitchen_index.php">Queue</a></li>
        <li><a href="kitchen_history.php">History</a></li>
        <li><a href="kitchen_logout.php">Logout</a></li>
    </ul>
</nav>

<div class="container">

<h1>🍽 Completed Orders</h1>

<!-- ================= HISTORY CONTAINER ================= -->
<div id="history-container">

<?php if(empty($history)): ?>

    <div class="card">
        <p>No completed orders yet.</p>
    </div>

<?php else: ?>

    <?php foreach($history as $id => $order): ?>

    <div class="card">

        <strong><?= htmlspecialchars($order['full_name'] ?? 'N/A') ?></strong><br>
        <small><?= htmlspecialchars($order['email'] ?? 'Cashier') ?></small><br>

        <span class="badge badge-success">DONE</span>

        <hr>

        <p><b>Order ID:</b> <?= htmlspecialchars($id) ?></p>
        <p><b>Total:</b> ₱<?= number_format($order['total'] ?? 0, 2) ?></p>
        <p><b>Completed:</b> <?= htmlspecialchars($order['completed_at'] ?? '') ?></p>

        <hr>

        <b>Items:</b><br>

        <?php foreach(($order['products'] ?? []) as $item): ?>
            • <?= htmlspecialchars($item['name'] ?? 'Unknown') ?> 
            × <?= intval($item['qty'] ?? 0) ?><br>
        <?php endforeach; ?>

    </div>

    <?php endforeach; ?>

<?php endif; ?>

</div>

</div>

<!-- ================= SAFE LIVE UPDATE ================= -->
<script>
let lastHTML = document.getElementById("history-container").innerHTML;

async function loadHistory() {
    try {
        const res = await fetch("fetch_kitchen_history.php");

        if(!res.ok) return;

        const data = await res.json();

        const container = document.getElementById("history-container");

        // ✅ ONLY UPDATE IF VALID DATA EXISTS
        if(data && data.html && data.html.trim() !== ""){
            container.innerHTML = data.html;
            lastHTML = data.html;
        }

    } catch (err) {
        console.log("History fetch error:", err);

        // ❗ RESTORE LAST STATE (PREVENT VANISH)
        document.getElementById("history-container").innerHTML = lastHTML;
    }
}

// slower refresh = stable system
setInterval(loadHistory, 5000);
</script>

</body>
</html>