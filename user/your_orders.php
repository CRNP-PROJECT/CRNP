<?php
session_start();

include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

if(!isset($_SESSION['email'])){
    header("Location: login.php");
    exit;
}

$rdb = new firebaseRDB($databaseURL);
$user_email = $_SESSION['email'];

$orders_raw = $rdb->retrieve("/orders");
$orders = json_decode($orders_raw, true);

$bookings_raw = $rdb->retrieve("/bookings");
$bookings = json_decode($bookings_raw, true);

$cartCount = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;

if(isset($_GET['status'])){
    if($_GET['status'] == "success"){
        echo "<p style='color:green;'>Profile updated successfully!</p>";
    } elseif($_GET['status'] == "error"){
        echo "<p style='color:red;'>Update failed. Please try again.</p>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../styles.css">
<title>Your Orders & Bookings</title>
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="navbar-brand">CRNP</a>
    <ul class="navbar-menu">
        <li><a href="index.php">Home</a></li>
        <li><a href="products.php">Products</a></li>
        <li><a href="cart.php">Cart (<?php echo $cartCount; ?>)</a></li>
        <li><a href="../logout.php">Logout</a></li>
    </ul>
</nav>

<div class="container">
    <h1 class="page-title mb-3">Your Orders</h1>

    <?php
    $hasOrders = false;

    if(is_array($orders)){
        foreach($orders as $id => $order){
            if(($order['user_email'] ?? '') == $user_email){
                $hasOrders = true;

                // ✅ GET KITCHEN STATUS
                $kitchen_status = $order['kitchen_status'] ?? 'pending';
    ?>
    <div class="card mb-2">
        <div class="flex-between mb-1">
            <strong>Order ID:</strong>

            <!-- ✅ DISPLAY KITCHEN STATUS -->
            <span class="badge badge-<?php echo htmlspecialchars($kitchen_status); ?>">
                <?php echo strtoupper(htmlspecialchars($kitchen_status)); ?>
            </span>
        </div>

        <p class="mb-1">Total: ₱<?php echo number_format($order['total'], 2); ?></p>

        <p class="text-muted mb-1">Products:</p>
        <ul style="margin-left: 20px; color: var(--text-light);">
            <?php foreach($order['products'] as $p): ?>
                <li><?php echo htmlspecialchars($p['name']); ?> (x<?php echo intval($p['qty']); ?>)</li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
            }
        }
    }

    if(!$hasOrders){
        echo '<div class="card"><p class="text-muted text-center">No orders found.</p></div>';
    }
    ?>

    <h1 class="page-title mb-3 mt-3">Your Bookings</h1>

    <?php
    $hasBookings = false;

    if(is_array($bookings)){
        foreach($bookings as $id => $b){
            if(($b['user_email'] ?? '') == $user_email){
                $hasBookings = true;
    ?>
    <div class="card mb-2">

        <!-- STATUS -->
        <div class="flex-between mb-1">
            <strong>Date & Time:</strong>
            <span class="badge badge-<?php echo htmlspecialchars($b['status'] ?? 'pending'); ?>">
                <?php echo strtoupper(htmlspecialchars($b['status'] ?? 'PENDING')); ?>
            </span>
        </div>

        <p class="mb-1"><?php echo htmlspecialchars($b['appointment_time']); ?></p>

        <p class="mb-1"><strong>Contact:</strong> <?php echo htmlspecialchars($b['contact_number']); ?></p>

        <?php if(!empty($b['address'])): ?>
            <p class="mb-1"><strong>Address:</strong> <?php echo htmlspecialchars($b['address']); ?></p>
        <?php endif; ?>

        <p class="mb-1"><strong>Tables:</strong> <?php echo intval($b['tables_qty']); ?></p>
        <p class="mb-1"><strong>Chairs:</strong> <?php echo intval($b['chairs_qty']); ?></p>

        <p class="mb-1"><strong>Skirting:</strong></p>

        <p style="margin-left: 16px; color: var(--text-light);">
            <?php 
            if(isset($b['skirting']) && is_array($b['skirting'])){
                foreach($b['skirting'] as $s){
                    $color = is_array($s['color']) ? implode(", ", $s['color']) : $s['color'];
                    $qty = is_array($s['qty']) ? implode(", ", $s['qty']) : $s['qty'];
                    echo "- " . htmlspecialchars((string)$color) . " (x" . intval($qty) . ")<br>";
                }
            } elseif(isset($b['skirting_color']) && is_array($b['skirting_color'])) {
                $color = implode(", ", $b['skirting_color']);
                $qty = is_array($b['skirting_qty']) ? implode(", ", $b['skirting_qty']) : $b['skirting_qty'];
                echo "- " . htmlspecialchars((string)$color) . " (x" . intval($qty) . ")";
            } elseif(!empty($b['skirting_color'])) {
                echo "- " . htmlspecialchars((string)$b['skirting_color']) . " (x" . intval($b['skirting_qty']) . ")";
            } else {
                echo "None";
            }
            ?>
        </p>

    </div>
    <?php
        }
    }
}

if(!$hasBookings){
    echo '<div class="card"><p class="text-muted text-center">No bookings found.</p></div>';
}
?>

</div>

</body>
</html>