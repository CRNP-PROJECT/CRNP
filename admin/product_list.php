<?php
session_start();

include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

$rdb = new firebaseRDB($databaseURL);

// DELETE
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $rdb->delete("/products", $id);
    header("Location: product_list.php");
    exit;
}

// 🔥 GET FILTER
$filter = $_GET['category'] ?? "All";

// FETCH PRODUCTS
$retrieve = $rdb->retrieve("/products");
$data = json_decode($retrieve, true) ?? [];

// 🔥 FILTER LOGIC
$products = [];

foreach ($data as $id => $product) {
    if ($filter === "All" || ($product['category'] ?? '') === $filter) {
        $products[$id] = $product;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Product List</title>

<link rel="stylesheet" href="../style.css">
</head>

<body class="product-list-body">

<!-- ===== NAVBAR ===== -->
<nav class="navbar">

    <div class="navbar-brand-container">
        <img src="../img/logo.png" class="logo">
    </div>

    <ul class="navbar-menu">
        <li><a href="admin_index.php">Admin Dashboard</a></li>
        <li><a href="add_product.php">Add Product</a></li>
        <li><a href="product_list.php" class="active">Product List</a></li>
        <li><a href="admin_log.php">Logout</a></li>
    </ul>

</nav>

<!-- ===== MAIN ===== -->
<div class="product-list-container">

    <!-- HEADER -->
    <div class="product-list-header">

    <h1 class="product-list-title">Product List</h1>

    <div class="product-list-filters">
        <a href="?category=All"
           class="product-list-filter-btn <?php echo ($filter=='All')?'product-list-filter-active':''; ?>">
           All
        </a>

        <a href="?category=Food"
           class="product-list-filter-btn <?php echo ($filter=='Food')?'product-list-filter-active':''; ?>">
           Food
        </a>

        <a href="?category=Drinks"
           class="product-list-filter-btn <?php echo ($filter=='Drinks')?'product-list-filter-active':''; ?>">
           Alcohol
        </a>

        <a href="?category=Beverages"
           class="product-list-filter-btn <?php echo ($filter=='Beverages')?'product-list-filter-active':''; ?>">
           Beverages
        </a>
    </div>

</div>

       <div class="product-list-topbar">
         <a href="add_product.php" class="product-list-btn product-list-btn-primary">
        + Add Product
         </a>
        </div>

    </div>

    <!-- EMPTY STATE -->
    <?php if(empty($products)): ?>
        <div class="product-list-card">
            <p class="product-list-text-muted">No products found.</p>
        </div>

    <!-- TABLE -->
    <?php else: ?>
        <div class="product-list-table-wrapper">

            <table class="product-list-table">

                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($products as $id => $product): ?>

                    <tr>

                        <!-- IMAGE -->
                        <td>
                            <img src="uploads/<?php echo basename($product['image']); ?>" 
                                class="product-list-img"
                                alt="<?php echo htmlspecialchars($product['name']); ?>">
                        </td>

                        <!-- NAME -->
                        <td><?php echo htmlspecialchars($product['name']); ?></td>

                        <!-- CATEGORY -->
                        <td><?php echo htmlspecialchars($product['category'] ?? 'N/A'); ?></td>

                        <!-- PRICE -->
                        <td>₱<?php echo number_format($product['price'], 2); ?></td>

                        <!-- DESCRIPTION -->
                        <td><?php echo htmlspecialchars($product['description']); ?></td>

                        <!-- ACTIONS -->
                        <td class="product-list-actions">

                            <a href="edit_product.php?id=<?php echo $id; ?>"
                               class="product-list-btn product-list-btn-secondary product-list-btn-sm">
                               Edit
                            </a>

                            <a href="product_list.php?delete=<?php echo $id; ?>"
                               class="product-list-btn product-list-btn-danger product-list-btn-sm"
                               onclick="return confirm('Delete this product?')">
                               Delete
                            </a>

                        </td>

                    </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>
    <?php endif; ?>

</div>

</body>
</html>