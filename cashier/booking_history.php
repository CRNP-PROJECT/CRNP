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

/* GET BOOKINGS */
$data_raw = $rdb->retrieve("/bookings");
$data = json_decode($data_raw, true) ?? [];

/* FILTER */
$filter = $_GET['filter'] ?? 'all';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Booking History</title>

<link rel="stylesheet" href="../styles.css">

</head>

<body class="cashier-history-booking">

<!-- ✅ NAVBAR (NO DROPDOWN, MATCHES OTHER PAGES) -->
<header class="navbar">

    <div class="navbar-brand-container">
        <img src="../img/logo.png" class="logo" alt="Logo">
    </div>

    <div class="navbar-right">

        <ul class="navbar-menu">
            <li><a href="cashier_index.php">Dashboard</a></li>
            <li><a href="view_orders.php">Orders</a></li>
            <li><a href="booking_payment.php">Bookings</a></li>
            <li><a href="cashier_bookingHistory.php" class="active">Booking History</a></li>
            <li><a href="cashier_orderHistory">Order History</a></li>
            <li><a href="cashier_logout.php">Logout</a></li>
        </ul>
    </div>

</header>

<!-- HEADER -->
<div class="cashier-history-booking-header">
    <h1>Booking History</h1>
    <p>View accepted and rejected bookings</p>
</div>

<!-- FILTER -->
<div class="cashier-history-booking-tabs">
    <a href="?filter=all" class="<?= $filter=='all'?'active':'' ?>">All</a>
    <a href="?filter=accepted" class="<?= $filter=='accepted'?'active':'' ?>">Accepted</a>
    <a href="?filter=rejected" class="<?= $filter=='rejected'?'active':'' ?>">Rejected</a>
</div>

<!-- CONTENT -->
<div class="cashier-history-booking-container">

<?php
$hasData = false;

foreach($data as $id => $b):

    if(!is_array($b)) continue;

    $status = strtolower($b['status'] ?? '');

    /* FILTER */
    if($filter == 'accepted' && $status != 'accepted') continue;
    if($filter == 'rejected' && $status != 'rejected') continue;
    if($status != 'accepted' && $status != 'rejected') continue;

    $hasData = true;

    /* PAYMENT */
    $payment_status = strtoupper($b['payment_status'] ?? 'NO PAYMENT');

    if($payment_status === "NO_PAYMENT_REQUIRED"){
        $payment_status = "OVER THE COUNTER";
    }

    /* ITEMS */
    $items = [
        'Tables' => $b['tables_qty'] ?? 0,
        'Chairs' => $b['chairs_qty'] ?? 0,
        'Skirting Cloth' => $b['skirting_cloth_qty'] ?? 0
    ];
?>

<div class="cashier-history-booking-card">

    <!-- TOP -->
    <div class="cashier-history-booking-top">
        <h3><?= htmlspecialchars($b['full_name'] ?? 'Unknown') ?></h3>

        <span class="cashier-history-booking-badge <?= $status ?>">
            <?= strtoupper($status) ?>
        </span>
    </div>

    <!-- INFO -->
    <div class="cashier-history-booking-info">
        <p><b>Total:</b> ₱<?= number_format($b['booking_total'] ?? $b['total'] ?? 0, 2) ?></p>
        <p><b>Payment:</b> <?= htmlspecialchars($payment_status) ?></p>
    </div>

    <!-- ITEMS -->
    <div class="cashier-history-booking-items">
        <?php
        $hasItems = false;

        foreach($items as $name => $qty):
            if($qty > 0):
                $hasItems = true;
        ?>
            <div class="cashier-history-booking-item">
                <?= $name ?> x<?= intval($qty) ?>
            </div>
        <?php endif; endforeach; ?>

        <?php if(!$hasItems): ?>
            <div class="cashier-history-booking-item">No items</div>
        <?php endif; ?>
    </div>

    <!-- DATE -->
    <div class="cashier-history-booking-date">
        <?= htmlspecialchars($b['cashier_action_time'] ?? '') ?>
    </div>

</div>

<?php endforeach; ?>

<?php if(!$hasData): ?>
    <div class="cashier-history-booking-empty">
        No booking history found.
    </div>
<?php endif; ?>

</div>

</body>
</html>