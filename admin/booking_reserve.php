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

                <a href="product_list.php">Products</a>

                <a href="booking_reserve.php" class="active">Booking List</a>
                
                <li><a href="admin_log.php">Logout</a></li>
            </div>
        </li>
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
            <th>Date</th>
            <th>Items</th>
            <th>Payment</th>
        </tr>
        </thead>

        <tbody>

        <?php foreach($bookings as $id => $booking): ?>

        <tr>

        <td><?php echo $booking['full_name'] ?? ''; ?></td>
        <td><?php echo $booking['contact_number'] ?? ''; ?></td>
        <td><?php echo $booking['address'] ?? ''; ?></td>
        <td><?php echo $booking['appointment_time'] ?? ''; ?></td>

        <!-- ITEMS -->
        <td class="booking-reserve-items">
        <?php
        if(isset($booking['rent_items']) && is_array($booking['rent_items'])){

            foreach($booking['rent_items'] as $itemId => $qty){
                if($qty > 0){
                    echo "<div>Item ID: $itemId = $qty</div>";
                }
            }

        }else{
            echo "No items";
        }
        ?>
        </td>

        <!-- PAYMENT -->
        <td>
        <?php
        $method = $booking['payment_method'] ?? 'counter';

        if($method == "gcash"){
            echo "<span class='booking-reserve-badge gcash'>GCash</span>";
        }else{
            echo "<span class='booking-reserve-badge counter'>Over Counter</span>";
        }
        ?>
        </td>

        </tr>

        <?php endforeach; ?>

        </tbody>

        </table>

    </div>

</div>

</body>
</html>