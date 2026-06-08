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
                        "image" => "uploads/" . $imageName
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

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../style.css">

<title>Add Product</title>
</head>

<body class="admin-add-product-body">

<nav class="navbar">
    <div class="navbar-brand-container">
        <img src="../img/logo.png" class="logo">
    </div>

    <ul class="navbar-menu">
        <li><a href="admin_index.php">Admin Dashboard</a></li>
        <li><a href="add_product.php" class="active">Add Product</a></li>
        <li><a href="product_list.php">Product List</a></li>
        <li><a href="admin_log.php">Logout</a></li>
    </ul>
</nav>

<div class="admin-add-product-container">

    <div class="admin-add-product-card">

        <h1 class="admin-add-product-title">Add New Product</h1>

        <?php if($message): ?>
            <div class="admin-add-product-alert">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">

            <div class="admin-add-product-group">
                <label>Product Name</label>
                <input type="text" name="name" placeholder="Enter product name" required>
            </div>

            <div class="admin-add-product-group">
                <label>Price</label>
                <input type="number" step="0.01" name="price" placeholder="Enter price" required>
            </div>

            <div class="admin-add-product-group">
                <label>Category</label>
                <select name="category" required>
                    <option value="">Select Category</option>
                    <option value="Food">Food</option>
                    <option value="Drinks">Alcohol</option>
                    <option value="Beverages">Beverages</option>
                </select>
            </div>

            <div class="admin-add-product-group">
                <label>Description</label>
                <textarea name="description" placeholder="Enter product description" required></textarea>
            </div>

            <div class="admin-add-product-group">
                <label>Product Image</label>
                <input type="file" name="image" accept="image/*" required>
            </div>

            <button type="submit" class="admin-add-product-btn">
                Add Product
            </button>

        </form>

    </div>

</div>

</body>
</html>