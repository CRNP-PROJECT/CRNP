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

    // hide completed
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
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="../styles.css">

<title>Kitchen Dashboard</title>
</head>

<script>
async function loadKitchenOrders() {
    try {
        const res = await fetch("kitchen_fetch_orders.php");
        const data = await res.json();

        document.getElementById("walkin").innerHTML = data.walkin;
        document.getElementById("online").innerHTML = data.online;

    } catch (err) {
        console.log("Kitchen refresh error:", err);
    }
}

/* refresh every 3 seconds */
setInterval(loadKitchenOrders, 3000);
</script>

<body class="kitchen-dashboard">

<!-- NAVBAR (KEPT ONLY ONCE) -->
<nav class="navbar">
    <div class="navbar-brand-container">
        <img src="../img/logo.png" alt="Logo" class="logo">
    </div>

    <a href="kitchen_index.php" class="navbar-brand"></a>

    <ul class="navbar-menu">
        <li><a href="kitchen_index.php">Queue</a></li>
        <li><a href="kitchen_history.php">History</a></li>
        <li>
            <a href="kitchen_logout.php">
                <i class="fa-solid fa-right-from-bracket"></i> Logout
            </a>
        </li>
    </ul>
</nav>

<div class="container">

    <div class="page-header">
        <h1 class="page-title">
            <i class="fa-solid fa-fire-burner"></i> Kitchen Orders
        </h1>

        <p class="page-subtitle">
            <?php echo htmlspecialchars($_SESSION['kitchen_email']); ?>
        </p>
    </div>

    <div class="orders-grid">

    <?php if(empty($orders)): ?>
        <div class="card full">
            <p class="text-center text-muted">No orders found.</p>
        </div>
    <?php else: ?>

        <?php foreach($orders as $id => $order): 
            if(($order['status'] ?? '') === 'accepted'):
                $kitchen_status = $order['kitchen_status'] ?? 'pending';
        ?>

        <div class="card">

            <div class="card-header">
                <strong>
                    <i class="fa-solid fa-user"></i>
                    <?php echo htmlspecialchars($order['full_name']); ?>
                </strong>

                <span class="badge badge-<?php echo $kitchen_status; ?>">
                    <?php echo strtoupper($kitchen_status); ?>
                </span>
            </div>

            <div class="products">
                <?php foreach($order['products'] as $p): ?>
                    <div class="product-item">
                        <i class="fa-solid fa-bowl-food"></i>
                        <?php echo htmlspecialchars($p['name']); ?>
                        <span class="text-muted"> × <?php echo intval($p['qty']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if($kitchen_status !== 'done'): ?>
            <form method="POST" action="kitchen_process.php" class="card-actions">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="order_id" value="<?php echo $id; ?>">

                <button type="submit" name="status" value="preparing" class="btn btn-secondary">
                    Preparing
                </button>

                <button type="submit" name="status" value="ready" class="btn btn-primary">
                    Ready
                </button>

                <button type="submit" name="status" value="done" class="btn btn-success">
                    Done
                </button>
            </form>

            <?php else: ?>
            <div class="card-complete">
                <i class="fa-solid fa-circle-check"></i> Completed
            </div>
            <?php endif; ?>

        </div>

        <?php endif; endforeach; ?>

    <?php endif; ?>

    </div>

</div>

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