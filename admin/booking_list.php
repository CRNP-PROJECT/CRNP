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
<link rel="stylesheet" href="../style.css">


</head>
<body class="booking-list-body">

<nav class="navbar">

    <div class="navbar-brand-container">
        <img src="../img/logo.png" class="logo">
    </div>

    <ul class="navbar-menu">
        <li><a href="admin_index.php">Admin Dashboard</a></li>
        <li><a href="add_product.php">Add Product</a></li>
        <li><a href="product_list.php">Product List</a></li>
        <li><a href="booking_list.php" class="active">Rental Items</a></li>
        <li><a href="admin_log.php">Logout</a></li>
    </ul>

</nav>

<div class="booking-list-container">

    <h2 class="booking-list-title">Rental Items List</h2>

    <div class="booking-list-table-wrapper">

        <table class="booking-list-table">

            <thead>
                <tr>
                    <th>Item ID</th>
                    <th>Name</th>
                    <th>Price</th>
                </tr>
            </thead>

            <tbody>

            <?php if(empty($items)): ?>
                <tr>
                    <td colspan="3" class="booking-list-empty">
                        No items found
                    </td>
                </tr>
            <?php endif; ?>

            <?php foreach($items as $id => $item): ?>
                <?php if(!is_array($item)) continue; ?>

                <tr>
                    <td><?php echo $id; ?></td>
                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                    <td class="booking-list-price">
                        ₱<?php echo htmlspecialchars($item['price']); ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</div>

</body>
</html>