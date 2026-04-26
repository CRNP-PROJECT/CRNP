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
<link rel="stylesheet" href="../styles.css">
<style>
table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    padding: 10px;
    border: 1px solid #ccc;
}

.badge {
    padding: 5px 10px;
    border-radius: 5px;
    color: #fff;
}

.counter { background: #28a745; }
.gcash { background: #007bff; }
</style>
</head>
<body>

<h2>Booking List</h2>

<table>
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
<td>
<?php
if(isset($booking['rent_items']) && is_array($booking['rent_items'])){

    foreach($booking['rent_items'] as $itemId => $qty){
        if($qty > 0){
            echo "Item ID: $itemId = $qty <br>";
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
    echo "<span class='badge gcash'>GCash</span>";
}else{
    echo "<span class='badge counter'>Over Counter</span>";
}
?>
</td>

</tr>

<?php endforeach; ?>

</tbody>
</table>

</body>
</html>