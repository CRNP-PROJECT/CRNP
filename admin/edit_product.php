<?php
session_start();

include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

$rdb = new firebaseRDB($databaseURL);

$id = $_GET['id'] ?? null;

if (!$id) {
    die("Invalid product ID");
}

// FETCH PRODUCT
$retrieve = $rdb->retrieve("/products/$id");
$product = json_decode($retrieve, true);

if (!$product) {
    die("Product not found");
}

// UPDATE
if ($_SERVER['REQUEST_METHOD'] == "POST") {

    $name = $_POST['name'] ?? '';
    $price = $_POST['price'] ?? '';
    $description = $_POST['description'] ?? '';
    $category = $_POST['category'] ?? 'Food'; // default safe value

    $update = $rdb->update("/products", $id, [
        "name" => $name,
        "price" => $price,
        "description" => $description,
        "category" => $category,
        "image" => $product['image'] // keep old image
    ]);

    header("Location: product_list.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="edit_product_local.css"> </head>

<body class="edit-product-body">

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
            <li class="sidebar-menu-dropdown-item submenu-expanded">
                <button type="button" class="sidebar-submenu-trigger-btn">
                    <span class="submenu-trigger-left-block">
                        <i class="fa-solid fa-boxes-stacked nav-vector-icon"></i>
                        <span class="nav-item-label-text">Products Inventory</span>
                    </span>
                    <i class="fa-solid fa-chevron-down dropdown-arrow-indicator"></i>
                </button>
                <div class="nested-submenu-wrapper">
                    <a href="add_product.php">Add Product</a>
                    <a href="product_list.php" class="active">Product List</a>
                </div>
            </li>
            <li class="sidebar-menu-dropdown-item">
                <button type="button" class="sidebar-submenu-trigger-btn">
                    <span class="submenu-trigger-left-block">
                        <i class="fa-solid fa-calendar-check nav-vector-icon"></i>
                        <span class="nav-item-label-text">Reservations</span>
                    </span>
                    <i class="fa-solid fa-chevron-down dropdown-arrow-indicator"></i>
                </button>
                <div class="nested-submenu-wrapper">
                    <a href="booking_add.php">Add Booking Items</a>
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

    <div class="edit-product-container">

        <header class="edit-view-header-row">
           
            <a href="product_list.php" class="edit-back-to-list-link">
                <i class="fa-solid fa-arrow-left"></i> Back to Product List
            </a>
        </header>

        <div class="edit-product-card">
            <h1 class="edit-product-title">Edit Product</h1>
            <p class="edit-product-subtitle">Modify the item details and parameters below.</p>

            <form method="POST">

                <div class="edit-product-group">
                    <label class="edit-product-label">Product Name</label>
                    <input type="text" name="name"
                        value="<?php echo htmlspecialchars($product['name']); ?>"
                        class="edit-product-input" required autocomplete="off">
                </div>

                <div class="edit-product-group">
                    <label class="edit-product-label">Price (₱)</label>
                    <input type="number" step="0.01" name="price"
                        value="<?php echo htmlspecialchars($product['price']); ?>"
                        class="edit-product-input" required>
                </div>

                <div class="edit-product-group">
                    <label class="edit-product-label">Category</label>
                    <div class="edit-select-custom-wrapper">
                        <select name="category" class="edit-product-select" required>
                            <option value="Food"
                                <?php if(($product['category'] ?? '') == "Food") echo "selected"; ?>>
                                Food
                            </option>
                            <option value="Drinks"
                                <?php if(($product['category'] ?? '') == "Drinks") echo "selected"; ?>>
                                Alcohol
                            </option>
                            <option value="Beverages"
                                <?php if(($product['category'] ?? '') == "Beverages") echo "selected"; ?>>
                                Beverages
                            </option>
                        </select>
                    </div>
                </div>

                <div class="edit-product-group">
                    <label class="edit-product-label">Description</label>
                    <textarea name="description" class="edit-product-textarea" required><?php echo htmlspecialchars($product['description']); ?></textarea>
                </div>

                <div class="edit-form-actions-row">
                    <button type="submit" class="edit-product-btn">
                        Update Product
                    </button>
                </div>

            </form>
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