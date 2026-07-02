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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="booking_add_local.css"> </head>
<body class="booking-add-body">

    <aside class="sidebar-navigation-aside" id="sidebar">
        <div class="sidebar-header-brand-container">
            <div class="brand-logo-wrapper">
                <span class="logo-mini-text">CNP</span>
                <span class="logo-full-text">Crates N' Plates</span>
            </div>
        </div>

        <ul class="sidebar-menu-list">
            <li>
                <a href="admin_index.php">
                    <i class="fa-solid fa-chart-pie nav-vector-icon"></i>
                    <span class="nav-item-label-text">Admin Dashboard</span>
                </a>
            </li>
            <li>
                <a href="create_cashier.php">
                    <i class="fa-solid fa-cash-register nav-vector-icon"></i>
                    <span class="nav-item-label-text">Cashier Portal</span>
                </a>
            </li>
            <li>
                <a href="create_kitchen.php">
                    <i class="fa-solid fa-utensils nav-vector-icon"></i>
                    <span class="nav-item-label-text">Kitchen Display</span>
                </a>
            </li>

            <li class="sidebar-menu-dropdown-item">
                <button type="button" class="sidebar-submenu-trigger-btn">
                    <span class="submenu-trigger-left-block">
                        <i class="fa-solid fa-boxes-stacked nav-vector-icon"></i>
                        <span class="nav-item-label-text">Products Inventory</span>
                    </span>
                    <i class="fa-solid fa-chevron-down dropdown-arrow-indicator"></i>
                </button>
                <div class="nested-submenu-wrapper">
                    <a href="add_product.php">Add Product</a>
                    <a href="product_list.php">Product List</a>
                </div>
            </li>

            <li class="sidebar-menu-dropdown-item submenu-expanded">
                <button type="button" class="sidebar-submenu-trigger-btn">
                    <span class="submenu-trigger-left-block">
                        <i class="fa-solid fa-calendar-check nav-vector-icon"></i>
                        <span class="nav-item-label-text">Reservations</span>
                    </span>
                    <i class="fa-solid fa-chevron-down dropdown-arrow-indicator"></i>
                </button>
                <div class="nested-submenu-wrapper">
                    <a href="booking_add.php" class="active">Add Booking Items</a>
                    <a href="booking_list.php">Booking List</a>
                     <a href="booking_reserve.php">Booking Reserve</a>
                </div>
            </li>

            <li>
                <a href="daily_report.php">
                    <i class="fa-solid fa-receipt nav-vector-icon"></i>
                    <span class="nav-item-label-text">Daily Report</span>
                </a>
            </li>
            
            <li class="sidebar-logout-container">
                <a href="admin_log.php">
                    <i class="fa-solid fa-right-from-bracket nav-vector-icon"></i>
                    <span class="nav-item-label-text">Logout</span>
                </a>
            </li>
        </ul>
    </aside>

    <main class="booking-add-main-content">

        <div class="booking-add-container">
            <div class="booking-add-card">
                
                <h2 class="booking-add-title">Booking Items (Chair / Table / Skirting)</h2>

                <form method="POST" action="admin_process.php" enctype="multipart/form-data" class="booking-add-form">

                    <?php if($edit_item): ?>
                        <input type="hidden" name="id" value="<?php echo $edit_id; ?>">
                        <input type="hidden" name="old_image" value="<?php echo $edit_item['image'] ?? ''; ?>">
                    <?php endif; ?>

                    <div class="booking-add-full">
                        <label class="booking-add-label">Item Name</label>
                        <input type="text" name="name" placeholder="Enter item name"
                        value="<?php echo $edit_item['display_name'] ?? $edit_item['name'] ?? ''; ?>"
                        class="booking-add-input" required>
                    </div>

                    <div class="booking-add-row-flex">
                        <div class="booking-add-col-6">
                            <label class="booking-add-label">Price (₱)</label>
                            <input type="number" name="price" placeholder="Enter Price"
                            value="<?php echo $edit_item['price'] ?? ''; ?>"
                            class="booking-add-input" required>
                        </div>
                        <div class="booking-add-col-6">
                            <label class="booking-add-label">Quantity Available</label>
                            <input type="number" name="quantity" min="1" placeholder="Enter quantity"
                            value="<?php echo $edit_item['quantity'] ?? ''; ?>"
                            class="booking-add-input" required>
                        </div>
                    </div>

                    <div class="booking-add-full">
                        <label class="booking-add-label">Item Media File</label>
                        <input type="file" name="image" class="booking-add-input booking-file-custom-style">
                    </div>

                    <?php if(!empty($edit_item['image'])): ?>
                        <div class="booking-add-full booking-add-preview">
                            <label class="booking-add-label">Current Set Image</label>
                            <div class="booking-img-frame">
                                <img src="<?php echo $edit_item['image']; ?>" class="booking-add-img">
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="booking-add-full booking-action-wrapper">
                        <?php if($edit_item): ?>
                            <button type="submit" name="update_rent_item" class="booking-add-btn edit-state-btn">
                                <i class="fa-solid fa-pen-to-square"></i> Update Existing Item
                            </button>
                        <?php else: ?>
                            <button type="submit" name="add_rent_item" class="booking-add-btn">
                                <i class="fa-solid fa-plus"></i> Save Item to Inventory
                            </button>
                        <?php endif; ?>
                    </div>

                </form>

                <hr class="booking-add-divider">

                <h3 class="booking-add-subtitle">Existing Booking Items</h3>

                <?php if(empty($items)): ?>
                    <div class="booking-empty-alert">
                        <i class="fa-solid fa-folder-open"></i>
                        <p>No storage rental items saved in the current directory database setup yet.</p>
                    </div>
                <?php else: ?>

                <div class="booking-add-table-wrapper">
                    <table class="booking-add-table">
                        <thead>
                            <tr>
                                <th>Item Image</th>
                                <th>Item Name</th>
                                <th>Unit Price</th>
                                <th>Available Stock</th>
                                <th style="text-align: center;">Control Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($items as $id => $item): ?>
                            <tr>
                                <td>
                                    <div class="table-img-container-box">
                                        <?php if(!empty($item['image'])): ?>
                                            <img src="<?php echo $item['image']; ?>" class="booking-add-img">
                                        <?php else: ?>
                                            <span class="no-img-placeholder-badge"><i class="fa-solid fa-image"></i></span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td class="item-primary-text-bold"><?php echo $item['display_name'] ?? $item['name']; ?></td>

                                <td class="item-currency-text-style">₱<?php echo number_format($item['price'], 2); ?></td>
                                <td><span class="item-stock-counter-pill"><?php echo $item['quantity'] ?? 0; ?> pcs</span></td>

                                <td class="booking-add-action">
                                    <a href="booking_add.php?edit=<?php echo $id; ?>" class="action-btn-edit">
                                        <i class="fa-solid fa-marker"></i> Edit
                                    </a>
                                    <a href="admin_process.php?delete_rent_item=<?php echo $id; ?>" 
                                       class="action-btn-delete"
                                       onclick="return confirm('Delete this item permanently?')">
                                        <i class="fa-solid fa-trash-can"></i> Delete
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php endif; ?>

            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const sidebarElement = document.getElementById('sidebar');
            const toggleButton = document.getElementById('sidebarToggle');

            if (toggleButton && sidebarElement) {
                toggleButton.addEventListener('click', () => {
                    sidebarElement.classList.toggle('collapsed');
                });
            }

            const submenuTriggers = document.querySelectorAll('.sidebar-submenu-trigger-btn');
            submenuTriggers.forEach(trigger => {
                trigger.addEventListener('click', (e) => {
                    const parentItem = trigger.closest('.sidebar-menu-dropdown-item');
                    if (sidebarElement && sidebarElement.classList.contains('collapsed')) return;

                    e.preventDefault();
                    parentItem.classList.toggle('submenu-expanded');
                });
            });
        });
    </script>
</body>
</html>