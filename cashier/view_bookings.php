<?php
session_start();

include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

if(!isset($_SESSION['cashier_email'])){
    header("Location: cashier_login.php");
    exit;
}

$rdb = new firebaseRDB($databaseURL);

$bookings_raw = $rdb->retrieve("/bookings");
$bookings = json_decode($bookings_raw, true) ?? [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../styles.css">

<title>Cashier Bookings</title>
</head>

<body class="view-booking">

<!-- NAVBAR -->
<header class="navbar">

    <div class="navbar-brand-container">
        <img src="../img/logo.png" class="logo">
    </div>

    <div class="navbar-right">
        <ul class="navbar-menu">
            <li><a href="cashier_index.php">Dashboard</a></li>
            <li><a href="view_bookings.php" class="active">Bookings</a></li>
            <li><a href="booking_history.php">History</a></li>
            <li><a href="booking_payment.php">Payment Status</a></li>
            <li><a href="cashier_logout.php">Logout</a></li>
        </ul>
    </div>

</header>

<!-- HEADER -->
<div class="view-booking-header">
    <h1>Cashier Bookings</h1>
    <p>Manage all reservation requests</p>
</div>

<!-- CONTENT -->
<div class="view-booking-container">

<?php if(empty($bookings)): ?>

    <div class="view-booking-empty">
        No bookings found
    </div>

<?php else: ?>

<div class="view-booking-table-wrapper">

<table class="view-booking-table">

<thead>
<tr>
    <th>Email</th>
    <th>Name</th>
    <th>Contact</th>
    <th>Schedule</th>
    <th>Items</th>
    <th>Total</th>
    <th>Status</th>
    <th>Action</th>
    <th>Created</th>
</tr>
</thead>

<tbody>

<?php foreach($bookings as $id => $b):

    $status = $b['status'] ?? 'pending';
    if($status !== 'pending') continue;
?>

<tr>

<td><?= htmlspecialchars($b['user_email'] ?? '') ?></td>
<td><?= htmlspecialchars($b['full_name'] ?? '') ?></td>
<td><?= htmlspecialchars($b['contact_number'] ?? '') ?></td>
<td>
<?= !empty($b['appointment_time']) 
    ? date('F d, Y - h:i A', strtotime($b['appointment_time'])) 
    : '-' ?>
</td>
<td>
<?php 
if(isset($b['items']) && is_array($b['items'])) {
    foreach($b['items'] as $item){
        echo htmlspecialchars($item['name']) . 
        " (x" . intval($item['qty']) . 
        ") - ₱" . number_format($item['subtotal'], 2) . "<br>";
    }
} else {
    echo "-";
}
?>
</td>

<td><b>₱<?= number_format($b['booking_total'] ?? 0, 2) ?></b></td>

<td>
    <span class="view-booking-badge pending">PENDING</span>
</td>

<td>
<div class="view-booking-actions">

<form method="POST" action="cashier_process.php">
    <input type="hidden" name="action" value="update_booking_status">
    <input type="hidden" name="booking_id" value="<?= $id ?>">
    <input type="hidden" name="status" value="accepted">
    <button class="view-booking-btn accept">Accept</button>
</form>

<form method="POST" action="cashier_process.php">
    <input type="hidden" name="action" value="update_booking_status">
    <input type="hidden" name="booking_id" value="<?= $id ?>">
    <input type="hidden" name="status" value="rejected">
    <button class="view-booking-btn reject">Reject</button>
</form>

</div>
</td>

<td><?= htmlspecialchars($b['created_at'] ?? '') ?></td>

</tr>

<?php endforeach; ?>

</tbody>
</table>

</div>

<?php endif; ?>

</div>

</body>
</html>