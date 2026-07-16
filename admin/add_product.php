<?php
session_start();

include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

$message = "";

if ($_SERVER['REQUEST_METHOD'] === "POST") {
    $name = $_POST['name'] ?? '';
    $price = $_POST['price'] ?? '';
    $description = $_POST['description'] ?? '';
    $category = $_POST['category'] ?? '';
    $imageFile = $_FILES['image'] ?? null;

    if ($name == "" || $price == "" || $description == "" || $category == "" || !$imageFile) {
        $message = "All fields are required.";
    } else {

        // ✅ VALIDATE CATEGORY
        $allowedCategories = ["Food", "Drinks", "Beverages"];
        if (!in_array($category, $allowedCategories)) {
            $message = "Invalid category selected.";
        } else {

            $uploadDir = __DIR__ . "/uploads/";
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            $imageName = time() . "_" . basename($imageFile['name']);
            $targetFile = $uploadDir . $imageName;

            if (move_uploaded_file($imageFile['tmp_name'], $targetFile)) {

                try {
                    $rdb = new firebaseRDB($databaseURL);

                    $insert = $rdb->insert("/products", [
                        "name" => $name,
                        "price" => $price,
                        "description" => $description,
                        "category" => $category, // 🔥 IMPORTANT
                        "image" => "../admin/uploads/" . $imageName
                    ]);

                    if ($insert) {
                        header("Location: product_list.php");
                        exit;
                    } else {
                        $message = "Failed to add product.";
                    }

                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                }

            } else {
                $message = "Failed to upload image.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../style.css"> 
</head>

<body class="admin-add-product-body">

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
            <li class="sidebar-menu-dropdown-item open">
                <button type="button" class="sidebar-submenu-trigger-btn">
                    <span class="submenu-trigger-left-block">
                        <i class="fa-solid fa-boxes-stacked nav-vector-icon"></i>
                        <span class="nav-item-label-text">Products Inventory</span>
                    </span>
                    <i class="fa-solid fa-chevron-down dropdown-arrow-indicator"></i>
                </button>
                <div class="nested-submenu-wrapper">
                    <a href="add_product.php" class="active">Add Product</a>
                    <a href="product_list.php">Product List</a>
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

    <main class="admin-add-product-main-content">

        <button class="sidebar-brand-toggle-btn" id="sidebarToggle" type="button">
            <i class="fa-solid fa-bars"></i>
        </button>

        <div class="admin-add-product-container">
            <div class="admin-add-product-card">

                <h1 class="admin-add-product-title">Add New Product</h1>

                <?php if(!empty($message)): ?>
                    <div class="admin-add-product-alert">
                        <i class="fa-solid fa-circle-info"></i>
                        <span><?php echo htmlspecialchars($message); ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" class="admin-add-product-form">

                    <div class="admin-add-product-row">
                        <div class="admin-add-product-group col-8">
                            <label class="admin-add-product-label">Product Name</label>
                            <input type="text" class="admin-add-product-input" name="name" placeholder="Enter product name" required>
                        </div>

                        <div class="admin-add-product-group col-4">
                            <label class="admin-add-product-label">Price (₱)</label>
                            <input type="number" step="0.01" class="admin-add-product-input" name="price" placeholder="0.00" required>
                        </div>
                    </div>

                    <div class="admin-add-product-group">
                        <label class="admin-add-product-label">Category</label>
                        <div class="admin-select-wrapper">
                            <select class="admin-add-product-select" name="category" required>
                                <option value="" disabled selected hidden>Select Category</option>
                                <option value="Food">Food</option>
                                <option value="Drinks">Alcohol</option>
                                <option value="Beverages">Beverages</option>
                            </select>
                        </div>
                    </div>

                    <div class="admin-add-product-group">
                        <label class="admin-add-product-label">Description</label>
                        <textarea class="admin-add-product-textarea" name="description" placeholder="Write comprehensive product parameters or specifications..." required></textarea>
                    </div>

                    <div class="admin-add-product-group">
                        <label class="admin-add-product-label">Product Image Attachment</label>
                        <div class="admin-file-upload-dropzone">
                            <i class="fa-solid fa-cloud-arrow-up cloud-icon"></i>
                            <input type="file" class="admin-add-product-file-input" name="image" accept="image/*" required>
                            <p class="file-help-text">Click to browse or drag image files directly here</p>
                        </div>
                    </div>

                    <div class="admin-action-block">
                        <button type="submit" class="admin-add-product-btn">
                            <i class="fa-solid fa-plus"></i> Save Product to Inventory
                        </button>
                    </div>

                </form>

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
                    
                    // Check if sidebar is collapsed - ignore accordion dropdown click states if closed
                    if (sidebarElement && sidebarElement.classList.contains('collapsed')) return;

                    e.preventDefault();
                    parentItem.classList.toggle('submenu-expanded');
                });
            });
        });
    </script>

</body>
</html>