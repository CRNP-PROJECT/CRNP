<?php
session_start();

include(__DIR__ . "/../config.php"); 
include(__DIR__ . "/../firebaseRDB.php"); 

// 🔐 ADMIN CHECK
if(!isset($_SESSION['admin_id'])){
    header("Location: admin_login.php");
    exit;
}

$rdb = new firebaseRDB($databaseURL);

// 📦 GET BOOKINGS
$bookings_raw = $rdb->retrieve("/bookings");
$bookings = json_decode($bookings_raw, true);

if(!is_array($bookings)){
    $bookings = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Booking List</title>
<link rel="stylesheet" href="../style.css">
</head>

<body class="booking-reserve-body">

<nav class="navbar">

    <div class="navbar-brand-container">
        <img src="../img/logo.png" class="logo">
    </div>

    <ul class="navbar-menu">
        <a href="admin_index.php" class="navbar-brand">Admin Dashboard</a>
        <a href="booking_add.php">Booking Items</a>
        <a href="booking_reserve.php" class="active">Booking List</a>
        <li><a href="admin_log.php">Logout</a></li>
    </ul>

</nav>

<div class="booking-reserve-container">

<h2 class="booking-reserve-title">Booking List</h2>

<div class="booking-reserve-table-wrapper">

<table class="booking-reserve-table">

<thead>
<tr>
    <th>Name</th>
    <th>Contact</th>
    <th>Address</th>
    <th>Booking Date</th>
    <th>Items</th>
    <th>Total</th>
    <th>Payment</th>
    <th>Delivered</th>
    <th>Delivered by</th>
    <th>Return Date</th>
    <th>Status</th>
    <th>Action</th>
</tr>
</thead>

<tbody>

<?php foreach($bookings as $id => $booking): ?>

<tr>

    <td><?= $booking['full_name'] ?? '' ?></td>
    <td><?= $booking['contact_number'] ?? '' ?></td>
    <td><?= $booking['address'] ?? '' ?></td>

    <!-- BOOKING DATE -->
    <td>
        <?= !empty($booking['appointment_time'])
            ? date("M d, Y h:i A", strtotime($booking['appointment_time']))
            : "" ?>
    </td>

    <!-- ITEMS -->
    <td>
    <?php
    if(isset($booking['items']) && is_array($booking['items'])){

        foreach($booking['items'] as $item){

            $name = $item['name'] ?? 'Item';
            $qty = $item['qty'] ?? 0;
            $price = $item['price'] ?? 0;

            echo "<div>• {$name} (x{$qty}) - ₱{$price}</div>";
        }

    }else{
        echo "No items";
    }
    ?>
    </td>

    <!-- TOTAL -->
    <td>₱<?= number_format($booking['booking_total'] ?? 0, 2) ?></td>

    <!-- PAYMENT -->
    <td>
    <?php
    $method = $booking['payment_method'] ?? 'counter';

    echo ($method == "gcash")
        ? "<span style='color:blue;font-weight:bold;'>GCash</span>"
        : "<span style='color:gray;font-weight:bold;'>Counter</span>";
    ?>
    </td>

    <!-- DELIVERED -->
    <td>
        <?= !empty($booking['delivered_at'])
            ? date("M d, Y h:i A", strtotime($booking['delivered_at']))
            : "Not Delivered" ?>
    </td>

    <!-- DELIVERED BY -->
    <td>
        <?= htmlspecialchars($booking['delivery_note'] ?? '-') ?>
    </td>

    <!-- RETURN DATE -->
    <td>
        <?= !empty($booking['returned_at'])
            ? date("M d, Y h:i A", strtotime($booking['returned_at']))
            : '-' ?>
    </td>

    <!-- STATUS -->
    <td>
        <?php
        $status = $booking['status'] ?? 'active';

        if($status === 'returned'){
            echo "<span style='color:green;font-weight:bold;'>Returned</span>";
        }elseif($status === 'done'){
            echo "<span style='color:orange;font-weight:bold;'>Delivered</span>";
        }else{
            echo "<span style='color:red;font-weight:bold;'>Active</span>";
        }
        ?>
    </td>

    <!-- ACTION -->
    <td>

        <?php if(($booking['status'] ?? '') === 'done'): ?>

        <form method="POST" action="admin_process.php">

            <!-- IMPORTANT -->
            <input type="hidden" name="booking_id" value="<?= $id ?>">

            <button type="submit"
                    name="return_booking"
                    onclick="return confirm('Confirm return?')"
                    style="padding:6px 10px;background:#28a745;color:#fff;border:none;border-radius:5px;">
                Mark Returned
            </button>

        </form>

        <?php elseif(($booking['status'] ?? '') === 'returned'): ?>

            <span style="color:green;font-weight:bold;">Completed</span>

        <?php else: ?>

            <span>-</span>

        <?php endif; ?>

    </td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

</div>

</body>
</html>