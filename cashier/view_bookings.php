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

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../styles.css">

<title>Cashier Bookings</title>
</head>

<body class="cashier-booking">

<!-- ================= NAVBAR (KEPT AS YOU REQUESTED) ================= -->
<nav class="navbar">

    <div class="navbar-brand-container">
        <img src="../img/logo.png" class="logo" alt="Logo">
    </div>

    <ul class="navbar-menu">

        <li>
            <a href="cashier_index.php">
                <i class="fa-solid fa-chart-line"></i> Dashboard
            </a>
        </li>

        <li>
            <a href="create_order.php">
                <i class="fa-solid fa-bag-shopping"></i> New Order
            </a>
        </li>

        <li>
            <a href="view_orders.php">
                <i class="fa-solid fa-receipt"></i> Orders
            </a>
        </li>

        <li>
            <a href="view_bookings.php" class="active">
                <i class="fa-solid fa-calendar-days"></i> Bookings
            </a>
        </li>

        <li>
            <a href="cashier_logout.php">
                <i class="fa-solid fa-right-from-bracket"></i> Logout
            </a>
        </li>

    </ul>
</nav>

<!-- ================= HEADER ================= -->
<div class="cashier-booking-page-header">

    <h1 class="cashier-booking-page-title">
        <i class="fa-solid fa-calendar-check"></i> Cashier Bookings
    </h1>

    <p class="cashier-booking-page-subtitle">
        Manage all reservation requests
    </p>

</div>

<!-- ================= CONTENT ================= -->
<div class="cashier-booking-container">

<?php if(empty($bookings)): ?>

    <div class="cashier-booking-empty-box">
        <i class="fa-regular fa-folder-open"></i>
        <p>No bookings found</p>
    </div>

<?php else: ?>

<div class="cashier-booking-table-wrapper">

<table class="cashier-booking-table">

<thead>
<tr>
    <th>Email</th>
    <th>Name</th>
    <th>Contact</th>
    <th>Date & Time</th>
    <th>Tables</th>
    <th>Chairs</th>
    <th>Skirting</th>
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
    <td><?= htmlspecialchars($b['appointment_time'] ?? '') ?></td>
    <td><?= intval($b['tables_qty'] ?? 0) ?></td>
    <td><?= intval($b['chairs_qty'] ?? 0) ?></td>

    <td class="cashier-booking-skirting">
        <?php 
        if(isset($b['skirting']) && is_array($b['skirting'])){
            foreach($b['skirting'] as $s){
                echo htmlspecialchars($s['color']) . " (x" . intval($s['qty']) . ")<br>";
            }
        } else {
            echo "-";
        }
        ?>
    </td>

    <td>
        <span class="cashier-booking-badge pending">PENDING</span>
    </td>

    <td>
        <div class="cashier-booking-actions">

            <form method="POST" action="cashier_process.php">
                <input type="hidden" name="action" value="update_booking_status">
                <input type="hidden" name="booking_id" value="<?= $id ?>">
                <input type="hidden" name="status" value="accepted">

                <button class="cashier-booking-btn cashier-booking-btn-accept" type="submit">
                    <i class="fa-solid fa-check"></i>
                </button>
            </form>

            <form method="POST" action="cashier_process.php">
                <input type="hidden" name="action" value="update_booking_status">
                <input type="hidden" name="booking_id" value="<?= $id ?>">
                <input type="hidden" name="status" value="rejected">

                <button class="cashier-booking-btn cashier-booking-btn-reject" type="submit">
                    <i class="fa-solid fa-xmark"></i>
                </button>
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