<?php
session_start();

include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

date_default_timezone_set("Asia/Manila");

if(!isset($_SESSION['cashier_email'])){
    header("Location: cashier_login.php");
    exit;
}

$rdb = new firebaseRDB($databaseURL);

// ✅ FIX: READ FROM BOOKINGS (NOT cashier_bookinghistory)
$data_raw = $rdb->retrieve("/bookings");
$data = json_decode($data_raw, true) ?? [];

$filter = $_GET['filter'] ?? 'all';
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../styles.css">
<title>Booking History</title>

<style>
.tab-bar{
    margin: 15px 0;
}

.tab-bar a{
    padding: 8px 15px;
    margin-right: 5px;
    text-decoration: none;
    border-radius: 5px;
    background: #eee;
    color: #333;
    font-weight: bold;
}

.tab-bar a.active{
    background: #333;
    color: #fff;
}

.card{
    background:#fff;
    padding:15px;
    margin-bottom:15px;
    border-radius:10px;
    box-shadow:0 2px 6px rgba(0,0,0,0.1);
}

.status-accepted{ color:green; font-weight:bold; }
.status-rejected{ color:red; font-weight:bold; }

.items{
    margin-top:10px;
    padding-left:15px;
}
</style>

</head>
<body>

<nav class="navbar">
    <a href="cashier_index.php" class="navbar-brand">Cashier Dashboard</a>
    <ul class="navbar-menu">
        <li><a href="view_bookings.php">View Bookings</a></li>
    </ul>
</nav>

<div class="container">

<h1>📋 Booking History</h1>

<div class="tab-bar">
    <a href="?filter=all" class="<?= $filter=='all'?'active':'' ?>">All</a>
    <a href="?filter=accepted" class="<?= $filter=='accepted'?'active':'' ?>">Accepted</a>
    <a href="?filter=rejected" class="<?= $filter=='rejected'?'active':'' ?>">Rejected</a>
</div>

<?php if(empty($data)): ?>
    <div class="card">No booking history yet.</div>
<?php else: ?>

<?php foreach($data as $id => $b):

    $status = strtolower($b['status'] ?? '');

    if($filter == 'accepted' && $status != 'accepted') continue;
    if($filter == 'rejected' && $status != 'rejected') continue;

    if($status != 'accepted' && $status != 'rejected') continue;

    $payment_status = strtoupper($b['payment_status'] ?? 'NO PAYMENT');
    if($payment_status == "NO_PAYMENT_REQUIRED"){
    $payment_status = "OVER THE COUNTER";
}
    $items = [
        'Tables' => $b['tables_qty'] ?? 0,
        'Chairs' => $b['chairs_qty'] ?? 0,
        'Skirting Cloth' => $b['skirting_cloth_qty'] ?? 0
    ];
?>

<div class="card">

    <p><b>Customer:</b> <?= htmlspecialchars($b['full_name'] ?? 'N/A') ?></p>

    <p><b>Total:</b> ₱<?= number_format($b['booking_total'] ?? $b['total'] ?? 0, 2) ?></p>

    <p>
        <b>Status:</b>
        <?php if($status == 'accepted'): ?>
            <span class="status-accepted">ACCEPTED</span>
        <?php else: ?>
            <span class="status-rejected">REJECTED</span>
        <?php endif; ?>
    </p>

    <p><b>Payment Status:</b> <?= $payment_status ?></p>

    <p><b>Items:</b></p>
    <div class="items">

        <?php foreach($items as $name => $qty): ?>
            <?php if($qty > 0): ?>
                <div>
                    • <?= $name ?> × <?= intval($qty) ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>

    </div>

    <p style="margin-top:10px; font-size:12px; color:gray;">
        <?= $b['cashier_action_time'] ?? '' ?>
    </p>

</div>

<?php endforeach; ?>

<?php endif; ?>

</div>

</body>
</html>