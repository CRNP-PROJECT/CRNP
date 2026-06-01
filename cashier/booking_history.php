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

<header class="navbar">

    <div class="navbar-brand-container">
        <img src="../img/logo.png" class="logo" alt="Logo">
    </div>

    <div class="navbar-right">

        <ul class="navbar-menu">
            <li><a href="cashier_index.php">Dashboard</a></li>
            <li><a href="view_bookings.php">Bookings</a></li>
            <li><a href="booking_history.php" class="active">Booking History</a></li>
            <li><a href="booking_payment.php">Payment Status</a></li>
            <li><a href="cashier_logout.php">Logout</a></li>
        </ul>
    </div>

</header>

<div class="cashier-history-booking-header">
    <h1>Booking History</h1>
    <p>View accepted, returned, and rejected bookings</p>
</div>

<div class="cashier-history-booking-tabs">
    <a href="?filter=all" class="<?= $filter=='all'?'active':'' ?>">All</a>
    <a href="?filter=accepted" class="<?= $filter=='accepted'?'active':'' ?>">Accepted</a>
    <a href="?filter=returned" class="<?= $filter=='returned'?'active':'' ?>">Returned</a>
    <a href="?filter=rejected" class="<?= $filter=='rejected'?'active':'' ?>">Rejected</a>
</div>

<div class="cashier-history-booking-container">

<?php
$hasData = false;

foreach($data as $id => $b):

    if(!is_array($b)) continue;

    $status = strtolower(trim($b['status'] ?? ''));

    // normalize statuses
    if(in_array($status, ['completed', 'finished'])){
        $status = 'done';
    }

    /* ================= FIX FILTER ================= */
    if ($filter == 'accepted' && $status != 'accepted') continue;

    if ($filter == 'rejected' && $status != 'rejected') continue;

    if ($filter == 'done' && $status != 'done') continue;

    if ($filter == 'returned' && $status != 'returned') continue;

    /* 🔥 IMPORTANT FIX: include returned in ALL */
    if ($filter == 'all' && !in_array($status, ['accepted','done','rejected','returned'])) continue;

    $hasData = true;

    $payment_status = strtoupper($b['payment_status'] ?? 'NO PAYMENT');

    if($payment_status === "NO_PAYMENT_REQUIRED"){
        $payment_status = "OVER THE COUNTER";
    }

    /* ================= FORMAT ITEMS ================= */
    $items = $b['items'] ?? [];

    /* ================= FORMAT DATES ================= */
    $createdAt = $b['created_at'] ?? null;
    $appointment = $b['appointment_time'] ?? null;
    $return_time = $b['return_time'] ?? null;

    $createdFormatted = $createdAt 
        ? date("M d, Y - h:i A", strtotime($createdAt)) 
        : "N/A";

    $appointmentFormatted = $appointment 
        ? date("M d, Y - h:i A", strtotime($appointment)) 
        : "N/A";

    $returnFormatted = $return_time 
        ? date("M d, Y - h:i A", strtotime($return_time)) 
        : "N/A";
?>

<div class="cashier-history-booking-card">

    <div class="cashier-history-booking-top">
        <h3><?= htmlspecialchars($b['full_name'] ?? 'Unknown') ?></h3>

        <span class="cashier-history-booking-badge <?= $status ?>">
            <?= strtoupper($status) ?>
        </span>
    </div>

    <div class="cashier-history-booking-info">
        <p><b>Total:</b> ₱<?= number_format($b['booking_total'] ?? $b['total'] ?? 0, 2) ?></p>
        <p><b>Payment:</b> <?= htmlspecialchars($payment_status) ?></p>
    </div>

    <div class="cashier-history-booking-items">

<?php
$hasItems = false;

if (is_array($items)) {

    foreach ($items as $item) {

        $name  = $item['name'] ?? 'Item';
        $qty   = isset($item['qty']) ? (int)$item['qty'] : 0;
        $price = isset($item['price']) ? (float)$item['price'] : 0;

        if ($qty > 0) {
            $hasItems = true;

            echo '<div class="cashier-history-booking-item">';
            echo htmlspecialchars($name) . " x{$qty} - ₱" . number_format($price, 2);
            echo '</div>';
        }
    }
}

if (!$hasItems) {
    echo '<div class="cashier-history-booking-item">No items</div>';
}
?>

    </div>

    <!-- DATE SECTION -->
    <div class="cashier-history-booking-date">

        <p><b>Created At:</b> <?= $createdFormatted ?></p>
        <p><b>Appointment:</b> <?= $appointmentFormatted ?></p>
        <p><b>Return Time:</b> <?= $returnFormatted ?></p>

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