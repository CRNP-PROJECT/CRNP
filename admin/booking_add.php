<?php
session_start();

include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

// 🔐 ADMIN CHECK
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

$rdb = new firebaseRDB($databaseURL);

/* ================= FETCH ITEMS ================= */
$items_raw = $rdb->retrieve("/rent_items");
$items = json_decode($items_raw, true) ?? [];

/* ================= EDIT MODE ================= */
$edit_id = $_GET['edit'] ?? null;
$edit_item = null;

if ($edit_id) {

    $all_items_raw = $rdb->retrieve("/rent_items");
    $all_items = json_decode($all_items_raw, true) ?? [];

    if (isset($all_items[$edit_id])) {
        $edit_item = $all_items[$edit_id];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Booking Items Manager</title>
<link rel="stylesheet" href="../style.css">
</head>
<body class="booking-add-body">

<nav class="navbar">

    <div class="navbar-brand-container">
        <img src="../img/logo.png" class="logo">
    </div>

    <ul class="navbar-menu">
             <a href="admin_index.php" class="navbar-brand">Admin Dashboard</a>

                <a href="booking_add.php" class="active">Booking Items</a>

                <a href="product_list.php">Products List</a>
                
                <li><a href="admin_log.php">Logout</a></li>
            </div>
        </li>
    </ul>

</nav>


<div class="booking-add-container">
<div class="booking-add-card">

<h2 class="booking-add-title">Booking Items (Chair / Table / Skirting)</h2>

<!-- ================= FORM ================= -->
<form method="POST" action="admin_process.php" enctype="multipart/form-data" class="booking-add-form">

    <?php if($edit_item): ?>
        <input type="hidden" name="id" value="<?php echo $edit_id; ?>">
        <input type="hidden" name="old_image" value="<?php echo $edit_item['image'] ?? ''; ?>">
    <?php endif; ?>

    <!-- ITEM NAME -->
    <div class="booking-add-full">
        <label class="booking-add-label">Item Name</label>
        <input type="text" name="name"  placeholder="Enter item name"
        value="<?php echo $edit_item['display_name'] ?? $edit_item['name'] ?? ''; ?>"
        class="booking-add-input" required>
    </div>

    <!-- PRICE -->
    <div class="booking-add-full">
        <label class="booking-add-label">Price</label>
        <input type="number" name="price"  placeholder="Enter Price"
        value="<?php echo $edit_item['price'] ?? ''; ?>"
        class="booking-add-input" required>
    </div>

    <!-- IMAGE -->
    <div class="booking-add-full">
        <label class="booking-add-label">Image</label>
        <input type="file" name="image" class="booking-add-input">
    </div>

    <!-- IMAGE PREVIEW -->
    <?php if(!empty($edit_item['image'])): ?>
        <div class="booking-add-full booking-add-preview">
            <img src="<?php echo $edit_item['image']; ?>" class="booking-add-img">
        </div>
    <?php endif; ?>

    <!-- BUTTON -->
    <div class="booking-add-full">
        <?php if($edit_item): ?>
            <button type="submit" name="update_rent_item" class="booking-add-btn">
                Update Item
            </button>
        <?php else: ?>
            <button type="submit" name="add_rent_item" class="booking-add-btn">
                Add Item
            </button>
        <?php endif; ?>
    </div>

</form>

<hr class="booking-add-divider">

<!-- ================= ITEM LIST ================= -->
<h3 class="booking-add-subtitle">Existing Booking Items</h3>

<?php if(empty($items)): ?>
    <p>No items yet.</p>
<?php else: ?>

<div class="booking-add-table-wrapper">
<table class="booking-add-table">

<tr>
    <th>Image</th>
    <th>Name</th>
    <th>Price</th>
    <th>Action</th>
</tr>

<?php foreach($items as $id => $item): ?>

<tr>
    <td>
        <?php if(!empty($item['image'])): ?>
            <img src="<?php echo $item['image']; ?>" class="booking-add-img">
        <?php else: ?>
            No image
        <?php endif; ?>
    </td>

    <td><?php echo $item['display_name'] ?? $item['name']; ?></td>

    <td>₱<?php echo $item['price']; ?></td>

    <td class="booking-add-action">
        <a href="booking_add.php?edit=<?php echo $id; ?>">Edit</a>
        <a href="admin_process.php?delete_rent_item=<?php echo $id; ?>"
           onclick="return confirm('Delete this item?')">
            Delete
        </a>
    </td>
</tr>

<?php endforeach; ?>

</table>
</div>

<?php endif; ?>

</div>
</div>

</body>
</html>