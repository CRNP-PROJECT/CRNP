<?php
session_start();

include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

if(!isset($_SESSION['cashier_email'])){
    header("Location: cashier_login.php");
    exit;
}

date_default_timezone_set("Asia/Manila");

$rdb = new firebaseRDB($databaseURL);

/* ================= FETCH ALL ORDERS ================= */
$raw = $rdb->retrieve("/orders");
$data = json_decode($raw, true);

if(!is_array($data)){
    $data = [];
}

$history = [];

/* ================= FILTER HISTORY ================= */
foreach($data as $order){

    if(!is_array($order)) continue;

    // ONLY COMPLETED ORDERS
    if(!in_array($order['status'], ['done', 'rejected'])) continue;

    // FIX ORDER TYPE
    if(empty($order['order_type'])){
        $order['order_type'] = 'online';
    }

    $history[] = $order;
}

/* ================= SORT LATEST FIRST ================= */
usort($history, function($a, $b){
    return strtotime($b['cashier_action_time'] ?? $b['created_at'])
        <=> strtotime($a['cashier_action_time'] ?? $a['created_at']);
});
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Cashier Order History</title>
<link rel="stylesheet" href="../styles.css">
</head>

<body>

<h2>🧾 Order History (Walk-in + Online)</h2>

<?php if(empty($history)): ?>
    <p>No history yet.</p>
<?php endif; ?>

<?php foreach($history as $order): ?>

<div style="border:1px solid #ccc; padding:12px; margin:10px; border-radius:8px;">

    <p><b>Order ID:</b> <?= htmlspecialchars($order['order_id']) ?></p>

    <p><b>Type:</b>
        <?php if(($order['order_type'] ?? '') === 'walkin'): ?>
            <span style="color:blue;">WALK-IN</span>
        <?php else: ?>
            <span style="color:green;">ONLINE</span>
        <?php endif; ?>
    </p>

    <p><b>Customer:</b>
        <?= htmlspecialchars($order['full_name'] ?? 'Online Customer') ?>
    </p>

    <p><b>Total:</b> ₱<?= number_format($order['total'] ?? 0, 2) ?></p>

    <p><b>Status:</b>
        <?php if($order['status'] === 'done'): ?>
            <span style="color:blue;">DONE</span>
        <?php elseif($order['status'] === 'rejected'): ?>
            <span style="color:red;">REJECTED</span>
        <?php else: ?>
            <span style="color:gray;">UNKNOWN</span>
        <?php endif; ?>
    </p>

    <p><b>Processed By:</b>
        <?= htmlspecialchars($order['processed_by'] ?? 'Cashier') ?>
    </p>

    <p><b>Date:</b>
        <?= htmlspecialchars($order['cashier_action_time'] ?? $order['created_at']) ?>
    </p>

</div>

<?php endforeach; ?>

</body>
</html>