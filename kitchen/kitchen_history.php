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

<<<<<<< HEAD
<body class="kitchen-history-page">
=======
<body>
>>>>>>> 784d58b7356ff90b699f7f25dfe2dd02149d3401

<nav class="navbar">
    <a href="kitchen_index.php" class="navbar-brand">Kitchen Dashboard</a>

    <ul class="navbar-menu">
        <li><a href="kitchen_index.php">Queue</a></li>
        <li><a href="kitchen_history.php">History</a></li>
        <li><a href="kitchen_logout.php">Logout</a></li>
    </ul>
</nav>

<div class="container">

<<<<<<< HEAD
<h1 class="page-title">Completed Orders</h1>

<!-- ✅ IMPORTANT: wrap everything -->
<div id="history-container">
=======
<h1 class="page-title">🍽 Completed Orders</h1>
>>>>>>> 784d58b7356ff90b699f7f25dfe2dd02149d3401

<?php if(empty($history)): ?>

    <div class="card">
        <p class="text-muted">No completed orders yet.</p>
    </div>

<?php else: ?>

    <?php foreach($history as $id => $order): ?>

        <div class="card mb-2">

<<<<<<< HEAD
=======
            <!-- ORDER HEADER -->
>>>>>>> 784d58b7356ff90b699f7f25dfe2dd02149d3401
            <div class="mb-2">
                <strong>
                    <?= htmlspecialchars($order['full_name'] ?? $order['customer_name'] ?? 'N/A') ?>
                </strong>

                <br>

<<<<<<< HEAD
                <small class="text-muted">
                    <?= htmlspecialchars($order['email'] ?? 'Cashier') ?>
=======
                <!-- EMAIL DISPLAY (IMPORTANT FIX) -->
                <small class="text-muted">
                    📧 <?= htmlspecialchars($order['email'] ?? 'Cashier') ?>
>>>>>>> 784d58b7356ff90b699f7f25dfe2dd02149d3401
                </small>

                <br>

                <span class="badge badge-success">DONE</span>
            </div>

<<<<<<< HEAD
            <p><b>Order ID:</b> <?= htmlspecialchars($order['order_id'] ?? $id) ?></p>
            <p><b>Total:</b> ₱<?= number_format($order['total'] ?? 0, 2) ?></p>
=======
            <!-- ORDER INFO -->
            <p><b>Order ID:</b> <?= htmlspecialchars($order['order_id'] ?? $id) ?></p>
            <p><b>Total:</b> ₱<?= number_format($order['total'] ?? 0, 2) ?></p>

>>>>>>> 784d58b7356ff90b699f7f25dfe2dd02149d3401
            <p><b>Completed:</b> <?= htmlspecialchars($order['completed_at'] ?? $order['created_at'] ?? '') ?></p>

            <hr>

<<<<<<< HEAD
=======
            <!-- ITEMS -->
>>>>>>> 784d58b7356ff90b699f7f25dfe2dd02149d3401
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
<<<<<<< HEAD
            <?php endforeach; else: ?>
=======
            <?php
                endforeach;
            else:
            ?>
>>>>>>> 784d58b7356ff90b699f7f25dfe2dd02149d3401
                <p>No items found</p>
            <?php endif; ?>

        </div>

    <?php endforeach; ?>

<?php endif; ?>

<<<<<<< HEAD
</div> <!-- END history-container -->

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

/* auto update */
setInterval(loadHistory, 3000);
</script>

=======
</div>

>>>>>>> 784d58b7356ff90b699f7f25dfe2dd02149d3401
</body>
</html>