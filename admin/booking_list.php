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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rental Items List</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../style.css">
<body class="booking-list-body">

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
                    <a href="booking_add.php">Add Booking Items</a>
                    <a href="booking_list.php" class="active">Booking List</a>
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

    <main class="booking-list-main-content">

        <button class="sidebar-brand-toggle-btn" id="sidebarToggle" type="button">
            <i class="fa-solid fa-bars"></i>
        </button>

        <div class="booking-list-container">

            <h2 class="booking-list-title">Rental Items List</h2>

            <div class="booking-list-table-wrapper">
                <table class="booking-list-table">
                    <thead>
                        <tr>
                            <th style="width: 150px;">Item ID</th>
                            <th>Rental Item Name</th>
                            <th style="width: 200px; text-align: right; padding-right: 40px;">Price</th>
                        </tr>
                    </thead>
                    <tbody>

                    <?php if(empty($items)): ?>
                        <tr>
                            <td colspan="3" class="booking-list-empty">
                                <i class="fa-solid fa-calendar-xmark" style="display:block; font-size: 2rem; margin-bottom: 12px; opacity: 0.5;"></i>
                                No rental items or event packages found in the database.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach($items as $id => $item): ?>
                        <?php if(!is_array($item)) continue; ?>

                        <tr>
                            <td>
                                <span class="booking-id-badge">#<?php echo $id; ?></span>
                            </td>
                            <td>
                                <span class="booking-item-name"><?php echo htmlspecialchars($item['name']); ?></span>
                            </td>
                            <td class="booking-list-price" style="text-align: right; padding-right: 40px;">
                                ₱<?php echo number_format((float)$item['price'], 2, '.', ','); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            
            // 1. DYNAMIC SIDEBAR DRAWER TOGGLE MODULE
            const toggleButton = document.getElementById('sidebarToggle');
            const sidebarElement = document.getElementById('sidebar');

            if (toggleButton && sidebarElement) {
                toggleButton.addEventListener('click', () => {
                    sidebarElement.classList.toggle('collapsed');
                });
            }

            // 2. SUBMENU ACCORDION TRIGGER ENGINE
            const submenuTriggers = document.querySelectorAll('.sidebar-submenu-trigger-btn');
            
            submenuTriggers.forEach(trigger => {
                trigger.addEventListener('click', (e) => {
                    const parentItem = trigger.closest('.sidebar-menu-dropdown-item');
                    
                    // Ignore accordion dropdown toggle animations if sidebar is collapsed completely
                    if (sidebarElement && sidebarElement.classList.contains('collapsed')) return;

                    e.preventDefault();
                    parentItem.classList.toggle('submenu-expanded');
                });
            });
        });
    </script>

</body>
</html>