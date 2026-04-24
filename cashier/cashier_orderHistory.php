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

$raw = $rdb->retrieve("/orders");
$data = json_decode($raw, true) ?? [];

$history = [];

foreach($data as $order){

    if(!is_array($order)) continue;

    $status = strtolower($order['status'] ?? '');
    $type   = strtolower($order['order_type'] ?? '');

    // AUTO-DETECT WALK-IN IF MISSING TYPE
    if($type === ''){
        if(isset($order['table_number']) || isset($order['cashier'])){
            $type = 'walkin';
        } else {
            $type = 'online';
        }
    }

    // INCLUDE EVERYTHING PROCESSED OR WALK-IN
    if(
        $type === 'walkin' ||
        in_array($status, ['accepted', 'rejected', 'done'])
    ){
        $order['order_type'] = $type;
        $history[] = $order;
    }
}

// SORT NEWEST FIRST
usort($history, function($a, $b){
    return strtotime($b['cashier_action_time'] ?? $b['created_at'] ?? 0)
        <=> strtotime($a['cashier_action_time'] ?? $a['created_at'] ?? 0);
});
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Order History</title>

<style>
.card {
    border: 1px solid #ccc;
    padding: 12px;
    margin: 10px;
    border-radius: 8px;
}

.walkin { color: blue; font-weight: bold; }
.online { color: green; font-weight: bold; }

.accepted { color: orange; font-weight: bold; }
.done { color: blue; font-weight: bold; }
.rejected { color: red; font-weight: bold; }
</style>

</head>

<body>

<h2>🧾 Order History</h2>

<?php if(empty($history)): ?>
    <p>No history yet.</p>
<?php endif; ?>

<?php foreach($history as $order): ?>

<div class="card">

    <p><b>Order ID:</b> <?= htmlspecialchars($order['order_id'] ?? '') ?></p>

    <p><b>Type:</b>
        <?php if(($order['order_type'] ?? '') === 'walkin'): ?>
            <span class="walkin">WALK-IN</span>
        <?php else: ?>
            <span class="online">ONLINE</span>
        <?php endif; ?>
    </p>

    <p><b>Customer:</b>
        <?= htmlspecialchars($order['full_name'] ?? 'Walk-in Customer') ?>
    </p>

    <p><b>Total:</b> ₱<?= number_format($order['total'] ?? 0, 2) ?></p>

    <!-- ✅ DISPLAY ORDERED ITEMS -->
    <p><b>Orders:</b></p>

    <?php if(!empty($order['products'])): ?>
        <ul>
            <?php foreach($order['products'] as $item): ?>
                <li>
                    <?= htmlspecialchars($item['name'] ?? 'Item') ?>
                    (x<?= intval($item['qty'] ?? 0) ?>)
                    - ₱<?= number_format($item['price'] ?? 0, 2) ?>
                    = ₱<?= number_format($item['subtotal'] ?? (($item['price'] ?? 0) * ($item['qty'] ?? 0)), 2) ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No items</p>
    <?php endif; ?>

    <p><b>Status:</b>
        <?php
        $status = strtolower($order['status'] ?? '');

        if($status === 'accepted'){
            echo "<span class='accepted'>ACCEPTED</span>";
        } elseif($status === 'done'){
            echo "<span class='done'>DONE</span>";
        } elseif($status === 'rejected'){
            echo "<span class='rejected'>REJECTED</span>";
        } else {
            echo "<span>UNKNOWN</span>";
        }
        ?>
    </p>

    <p><b>Processed By:</b>
        <?= htmlspecialchars($order['processed_by'] ?? 'Cashier') ?>
    </p>

    <p><b>Date:</b>
        <?= htmlspecialchars($order['cashier_action_time'] ?? $order['created_at'] ?? '') ?>
    </p>

</div>

<?php endforeach; ?>

</body>
</html>