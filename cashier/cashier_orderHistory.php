<?php
session_start();

include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

if(!isset($_SESSION['cashier_email'])){
    header("Location: cashier_login.php");
    exit;
}

$rdb = new firebaseRDB($databaseURL);

$raw = $rdb->retrieve("/orders");
$data = json_decode($raw, true) ?? [];

$history = [];

foreach($data as $order){

    if(!is_array($order)) continue;

    $status = strtolower($order['status'] ?? '');
    $type   = strtolower($order['order_type'] ?? '');

    if($type === ''){
        if(isset($order['table_number']) || isset($order['cashier'])){
            $type = 'walkin';
        } else {
            $type = 'online';
        }
    }

    if($type === 'walkin' || in_array($status, ['accepted','rejected','done'])){
        $order['order_type'] = $type;
        $history[] = $order;
    }
}

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

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../styles.css">

<title>Cashier History</title>
</head>

<body class="cashier-history">

<!-- NAVBAR -->
<nav class="navbar">

    <div class="navbar-brand-container">
        <img src="../img/logo.png" class="logo" alt="Logo">
        <span class="brand-text"> </span>
    </div>

    <ul class="navbar-menu">

        <li><a href="cashier_index.php">
            <i class="fa-solid fa-chart-line"></i> Dashboard
        </a></li>

        <li><a href="create_order.php">
            <i class="fa-solid fa-cart-plus"></i> Orders
        </a></li>

        <li><a class="active" href="cashier_history.php">
            <i class="fa-solid fa-clock-rotate-left"></i> History
        </a></li>

        <li><a href="cashier_logout.php">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a></li>

    </ul>
</nav>

<!-- HEADER -->
<div class="history-header">
    <h1><i></i> Cashier History</h1>
    <p>All processed orders (walk-in & online)</p>
</div>

<!-- CONTENT -->
<div class="history-container">

<?php if(empty($history)): ?>
    <div class="history-empty">
        No history found.
    </div>
<?php endif; ?>

<?php foreach($history as $order): ?>

<div class="history-card">

    <div class="history-top">
        <h3>#<?= htmlspecialchars($order['order_id'] ?? '') ?></h3>

        <span class="type <?= $order['order_type'] ?>">
            <?= strtoupper($order['order_type']) ?>
        </span>
    </div>

    <p><b>Customer:</b> <?= htmlspecialchars($order['full_name'] ?? 'Walk-in') ?></p>
    <p><b>Total:</b> ₱<?= number_format($order['total'] ?? 0, 2) ?></p>

    <p><b>Status:</b>
        <span class="status <?= strtolower($order['status'] ?? '') ?>">
            <?= strtoupper($order['status'] ?? 'unknown') ?>
        </span>
    </p>

    <div class="items">
        <b>Items:</b>
        <ul>
            <?php if(!empty($order['products'])): ?>
                <?php foreach($order['products'] as $item): ?>
                    <li>
                        <?= htmlspecialchars($item['name'] ?? '') ?> × <?= intval($item['qty'] ?? 0) ?>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>

    <p class="date">
        <i class="fa-regular fa-calendar"></i>
        <?= htmlspecialchars($order['cashier_action_time'] ?? $order['created_at'] ?? '') ?>
    </p>

</div>

<?php endforeach; ?>

</div>

</body>
</html>