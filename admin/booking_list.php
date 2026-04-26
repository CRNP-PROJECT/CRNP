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

// 📦 GET RENT ITEMS
$items_raw = $rdb->retrieve("/rent_items");
$items = json_decode($items_raw, true);

if(!is_array($items)){
    $items = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Rental Items List</title>

<link rel="stylesheet" href="../styles.css">

<style>
table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    padding: 12px;
    border: 1px solid #ccc;
}

th {
    background: #f4f4f4;
}
</style>

</head>
<body>

<nav class="navbar">
    <a href="admin_index.php" class="navbar-brand">Admin Dashboard</a>
    <ul class="navbar-menu">
        <li><a href="booking_add.php">Add booking Item</a></li>
        
    </ul>
</nav>

<h2>Rental Items List</h2>

<table>

<thead>
<tr>
    <th>Item ID</th>
    <th>Name</th>
    <th>Price</th>
</tr>
</thead>

<tbody>

<?php foreach($items as $id => $item): ?>

    <?php if(!is_array($item)) continue; ?>

    <tr>
        <td><?php echo $id; ?></td>
        <td><?php echo htmlspecialchars($item['name']); ?></td>
        <td>₱<?php echo htmlspecialchars($item['price']); ?></td>
    </tr>

<?php endforeach; ?>

</tbody>

</table>

</body>
</html>