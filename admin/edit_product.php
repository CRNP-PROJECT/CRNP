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
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../style.css">
<title>Edit Product</title>
</head>

<body class="edit-product-body">

<!-- ===== NAVBAR ===== -->
<nav class="navbar">

    <div class="navbar-brand-container">
        <img src="../img/logo.png" class="logo">
    </div>

    <ul class="navbar-menu">
        <li><a href="admin_index.php">Admin Dashboard</a></li>
        <li><a href="add_product.php">Add Product</a></li>
        <li><a href="product_list.php">Product List</a></li>
        <li><a href="admin_log.php">Logout</a></li>
    </ul>

</nav>

<!-- ===== MAIN ===== -->
<div class="edit-product-container">

    <div class="edit-product-card">

        <h1 class="edit-product-title">Edit Product</h1>

        <form method="POST">

            <!-- NAME -->
            <div class="edit-product-group">
                <label class="edit-product-label">Product Name</label>
                <input type="text" name="name"
                    value="<?php echo htmlspecialchars($product['name']); ?>"
                    class="edit-product-input" required>
            </div>

            <!-- PRICE -->
            <div class="edit-product-group">
                <label class="edit-product-label">Price</label>
                <input type="number" step="0.01" name="price"
                    value="<?php echo htmlspecialchars($product['price']); ?>"
                    class="edit-product-input" required>
            </div>

            <!-- CATEGORY -->
            <div class="edit-product-group">
                <label class="edit-product-label">Category</label>
                <select name="category" class="edit-product-select" required>

                    <option value="Food"
                        <?php if(($product['category'] ?? '') == "Food") echo "selected"; ?>>
                        Food
                    </option>

                    <option value="Drinks"
                        <?php if(($product['category'] ?? '') == "Drinks") echo "selected"; ?>>
                        Drinks
                    </option>

                    <option value="Beverages"
                        <?php if(($product['category'] ?? '') == "Beverages") echo "selected"; ?>>
                        Beverages
                    </option>

                </select>
            </div>

            <!-- DESCRIPTION -->
            <div class="edit-product-group">
                <label class="edit-product-label">Description</label>
                <textarea name="description" class="edit-product-textarea" required><?php echo htmlspecialchars($product['description']); ?></textarea>
            </div>

            <!-- BUTTON -->
            <button type="submit" class="edit-product-btn">
                Update Product
            </button>

        </form>

    </div>

</div>

</body>
</html>