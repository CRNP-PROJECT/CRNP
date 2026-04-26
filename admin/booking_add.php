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

<link rel="stylesheet" href="css/style.css">
</head>
<body>

<nav class="navbar">
    <a href="admin_index.php" class="navbar-brand">Admin Dashboard</a>
    <ul class="navbar-menu">
        <li><a href="booking_add.php">Booking Items</a></li>
        <li><a href="product_list.php">Products</a></li>
        <li><a href="admin_log.php">Logout</a></li>
    </ul>
</nav>

<div class="container">
<div class="card">

<h2>Booking Items (Chair / Table / Skirting)</h2>

<!-- ================= FORM ================= -->
<form method="POST" action="admin_process.php" enctype="multipart/form-data">

    <?php if($edit_item): ?>
        <input type="hidden" name="id" value="<?php echo $edit_id; ?>">
        <input type="hidden" name="old_image" value="<?php echo $edit_item['image'] ?? ''; ?>">
    <?php endif; ?>

    <div class="form-group">
        <label>Item Name</label>
        <input type="text" name="name"
        value="<?php echo $edit_item['display_name'] ?? $edit_item['name'] ?? ''; ?>"
        class="form-input" required>
    </div>

    <div class="form-group">
        <label>Price</label>
        <input type="number" name="price"
        value="<?php echo $edit_item['price'] ?? ''; ?>"
        class="form-input" required>
    </div>

    <!-- ✅ IMAGE INPUT -->
    <div class="form-group">
        <label>Image</label>
        <input type="file" name="image" class="form-input">
    </div>

    <!-- ✅ SHOW CURRENT IMAGE WHEN EDITING -->
    <?php if(!empty($edit_item['image'])): ?>
        <div class="form-group">
            <img src="<?php echo $edit_item['image']; ?>" width="100">
        </div>
    <?php endif; ?>

    <?php if($edit_item): ?>
        <button type="submit" name="update_rent_item" class="btn btn-primary">
            Update Item
        </button>
    <?php else: ?>
        <button type="submit" name="add_rent_item" class="btn btn-primary">
            Add Item
        </button>
    <?php endif; ?>

</form>

<hr>

<!-- ================= ITEM LIST ================= -->
<h3>Existing Booking Items</h3>

<?php if(empty($items)): ?>
    <p>No items yet.</p>
<?php else: ?>

<table border="1" width="100%" cellpadding="10">

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
            <img src="<?php echo $item['image']; ?>" width="60">
        <?php else: ?>
            No image
        <?php endif; ?>
    </td>

    <td><?php echo $item['display_name'] ?? $item['name']; ?></td>

    <td>₱<?php echo $item['price']; ?></td>

    <td>
        <a href="booking_add.php?edit=<?php echo $id; ?>">Edit</a> |
        <a href="admin_process.php?delete_rent_item=<?php echo $id; ?>"
           onclick="return confirm('Delete this item?')">
            Delete
        </a>
    </td>
</tr>

<?php endforeach; ?>

</table>

<?php endif; ?>

</div>
</div>

</body>
</html>