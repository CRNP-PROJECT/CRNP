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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="booking_list_local.css"> </head>

<body class="booking-reserve-body">

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

    <div class="booking-reserve-container">

        <header class="booking-view-header-row">
            <button class="sidebar-brand-toggle-btn" id="sidebarToggle" type="button">
                <i class="fa-solid fa-bars"></i>
            </button>
            <h2 class="booking-reserve-title">Booking List</h2>
            <div style="width: 40px;"></div> </header>

        <div class="booking-reserve-table-wrapper">
            <table class="booking-reserve-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>Address</th>
                        <th>Booking Date</th>
                        <th>Items Matrix</th>
                        <th>Total</th>
                        <th>Payment</th>
                        <th>Delivered</th>
                        <th>Delivered By</th>
                        <th>Return Date</th>
                        <th>Status</th>
                        <th style="text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody>

                <?php foreach($bookings as $id => $booking): ?>
                <?php
                $status = strtolower($booking['status'] ?? '');

                if(!in_array($status, ['accepted','done','returned'])){
                    continue;
                }
                ?>

                <tr>
                    <td class="primary-highlight-text"><?= $booking['full_name'] ?? '' ?></td>
                    <td><span class="data-subtle-phone"><?= $booking['contact_number'] ?? '' ?></span></td>
                    <td><div class="table-address-cell-container"><?= $booking['address'] ?? '' ?></div></td>

                    <td>
                        <div class="time-stamp-wrapper-box">
                            <?= !empty($booking['appointment_time'])
                                ? date("M d, Y", strtotime($booking['appointment_time'])) . "<br><span class='time-subtext'>" . date("h:i A", strtotime($booking['appointment_time'])) . "</span>"
                                : "" ?>
                        </div>
                    </td>

                    <td>
                        <div class="items-nested-list-wrapper">
                        <?php
                        if(isset($booking['items']) && is_array($booking['items'])){
                            foreach($booking['items'] as $item){
                                $name = $item['name'] ?? 'Item';
                                $qty = $item['qty'] ?? 0;
                                $price = $item['price'] ?? 0;

                                echo "<div class='nested-item-line'></i> {$name} <span class='qty-badge'>(x{$qty})</span> <span class='price-sub'>- ₱{$price}</span></div>";
                            }
                        }else{
                            echo "<span class='empty-placeholder-text'>No items attached</span>";
                        }
                        ?>
                        </div>
                    </td>

                    <td class="currency-highlight-cell">₱<?= number_format($booking['booking_total'] ?? 0, 2) ?></td>

                    <td>
                        <?php
                        $method = $booking['payment_method'] ?? 'counter';
                        echo ($method == "gcash")
                            ? "<span class='payment-method-pill gcash-pill'></i> GCash</span>"
                            : "<span class='payment-method-pill counter-pill'></i> Counter</span>";
                        ?>
                    </td>

                    <td>
                        <div class="time-stamp-wrapper-box">
                            <?= !empty($booking['delivered_at'])
                                ? date("M d, Y", strtotime($booking['delivered_at'])) . "<br><span class='time-subtext'>" . date("h:i A", strtotime($booking['delivered_at'])) . "</span>"
                                : "<span class='empty-placeholder-text'>Not Delivered</span>" ?>
                        </div>
                    </td>

                    <td class="delivery-agent-cell"><?= htmlspecialchars($booking['delivery_note'] ?? '-') ?></td>

                    <td>
                        <div class="time-stamp-wrapper-box">
                            <?= !empty($booking['returned_at'])
                                ? date("M d, Y", strtotime($booking['returned_at'])) . "<br><span class='time-subtext'>" . date("h:i A", strtotime($booking['returned_at'])) . "</span>"
                                : '<span class="empty-placeholder-text">-</span>' ?>
                        </div>
                    </td>

                    <td>
                        <?php
                        $status = $booking['status'] ?? 'active';

                        if($status === 'returned'){
                            echo "<span class='status-pill status-returned'><i class='fa-solid fa-circle-check'></i> Returned</span>";
                        }elseif($status === 'done'){
                            echo "<span class='status-pill status-delivered'><i class='fa-solid fa-truck-ramp-box'></i> Delivered</span>";
                        }else{
                            echo "<span class='status-pill status-active'><i class='fa-solid fa-bell'></i> Active</span>";
                        }
                        ?>
                    </td>

                    <td style="text-align: center;">
                        <?php if(($booking['status'] ?? '') === 'done'): ?>
                        <form method="POST" action="admin_process.php" style="display: inline-block;">
                            <input type="hidden" name="booking_id" value="<?= $id ?>">
                            <button type="submit" name="return_booking" onclick="return confirm('Confirm return?')" class="action-btn-returned-trigger">
                                <i class="fa-solid fa-square-check"></i> Mark Returned
                            </button>
                        </form>
                        <?php elseif(($booking['status'] ?? '') === 'returned'): ?>
                            <span class="completed-state-label"><i class="fa-solid fa-circle-check"></i> Completed</span>
                        <?php else: ?>
                            <span class="empty-placeholder-text">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>

                </tbody>
            </table>
        </div>
    </div>

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