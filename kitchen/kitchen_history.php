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

<body class="kitchen-history-page">

<nav class="navbar kitchen-navbar">
    <div class="navbar-brand-container">
        <img src="../img/logo.png" alt="Logo" class="logo">
        <span class="brand-text"> </span>
    </div>

    <ul class="navbar-menu">
        <li><a href="kitchen_index.php"><i class="fa-solid fa-fire-burner"></i> Queue</a></li>
        <li><a href="kitchen_history.php"><i class="fa-solid fa-clock-rotate-left"></i> History</a></li>
        <li><a href="kitchen_logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
    </ul>
</nav>

<div class="container">

<h1 class="page-title">🍽 Completed Orders</h1>

<div id="history-container">

<?php if(empty($history)): ?>

    <div class="card">
        <p class="text-muted">No completed orders yet.</p>
    </div>

<?php else: ?>

    <?php foreach($history as $id => $order): ?>

        <div class="card mb-2">

            <div class="mb-2">
                <strong>
                    <?= htmlspecialchars($order['full_name'] ?? $order['customer_name'] ?? 'N/A') ?>
                </strong>

                <br>

                <small class="text-muted">
                    📧 <?= htmlspecialchars($order['email'] ?? 'Cashier') ?>
                </small>

                <br>

                <span class="badge badge-success">DONE</span>
            </div>

            <p><b>Order ID:</b> <?= htmlspecialchars($order['order_id'] ?? $id) ?></p>
            <p><b>Total:</b> ₱<?= number_format($order['total'] ?? 0, 2) ?></p>
            <p><b>Completed:</b> <?= htmlspecialchars($order['completed_at'] ?? $order['created_at'] ?? '') ?></p>

            <hr>

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
            <?php endforeach; else: ?>
                <p>No items found</p>
            <?php endif; ?>

        </div>

    <?php endforeach; ?>

<?php endif; ?>

</div>

</div>

<!-- ================= LIVE UPDATE ================= -->
<script>
async function loadHistory() {
    try {
        const res = await fetch("fetch_kitchen_history.php");
        const data = await res.json();

        const container = document.getElementById("history-container");

        if (!data || Object.keys(data).length === 0) {
            container.innerHTML = `
                <div class="card">
                    <p class="text-muted">No completed orders yet.</p>
                </div>`;
            return;
        }

        let html = "";

        Object.keys(data).reverse().forEach(id => {
            const order = data[id];

            let itemsHTML = "";
            const items = order.products || order.items || [];

            items.forEach(item => {
                itemsHTML += `
                    <div style="padding:4px 0;">
                        ${item.name || 'Unknown'} × ${item.qty || 0}
                    </div>
                `;
            });

            html += `
                <div class="card mb-2">

                    <div class="mb-2">
                        <strong>${order.full_name || order.customer_name || 'N/A'}</strong><br>
                        <small class="text-muted">${order.email || 'Cashier'}</small><br>
                        <span class="badge badge-success">DONE</span>
                    </div>

                    <p><b>Order ID:</b> ${order.order_id || id}</p>
                    <p><b>Total:</b> ₱${parseFloat(order.total || 0).toFixed(2)}</p>
                    <p><b>Completed:</b> ${order.completed_at || order.created_at || ''}</p>

                    <hr>

                    <b>Items:</b>
                    ${itemsHTML || "<p>No items found</p>"}

                </div>
            `;
        });

        container.innerHTML = html;

    } catch (err) {
        console.error("Fetch error:", err);
    }
}

setInterval(loadHistory, 3000);
</script>

</body>
</html>