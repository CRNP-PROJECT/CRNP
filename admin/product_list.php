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
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="css/style.css">
<title>Product List</title>

<style>
.filter-btn {
    margin-right: 5px;
    padding: 6px 12px;
    text-decoration: none;
    border: 1px solid #ccc;
    border-radius: 5px;
}

.filter-active {
    background-color: #007bff;
    color: white;
}
</style>

</head>
<body>

<nav class="navbar">
    <a href="admin_index.php" class="navbar-brand">Admin Dashboard</a>
    <ul class="navbar-menu">
        <li><a href="add_product.php">Add Product</a></li>
        <li><a href="product_list.php">Product List</a></li>
        <li><a href="admin_log.php">Logout</a></li>
    </ul>
</nav>

<div class="container">
    <div class="page-header flex-between">
        <div>
            <h1 class="page-title">Product List</h1>

            <!-- 🔥 FILTER BUTTONS -->
            <div style="margin-top:10px;">
                <a href="?category=All" class="filter-btn <?php echo ($filter=='All')?'filter-active':''; ?>">All</a>
                <a href="?category=Food" class="filter-btn <?php echo ($filter=='Food')?'filter-active':''; ?>">Food</a>
                <a href="?category=Drinks" class="filter-btn <?php echo ($filter=='Drinks')?'filter-active':''; ?>">Drinks</a>
                <a href="?category=Beverages" class="filter-btn <?php echo ($filter=='Beverages')?'filter-active':''; ?>">Beverages</a>
            </div>

        </div>

        <a href="add_product.php" class="btn btn-primary">+ Add Product</a>
    </div>

    <?php if(empty($products)): ?>
        <div class="card">
            <p class="text-center text-muted">No products found.</p>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Name</th>
                        <th>Category</th> <!-- 🔥 NEW -->
                        <th>Price</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $id => $product): ?>
                    <tr>

                        <!-- 🔥 FIX IMAGE PATH -->
                        <td>
                            <img src="uploads/<?php echo basename($product['image']); ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                 style="width:60px; height:60px; object-fit:cover;">
                        </td>

                        <td><?php echo htmlspecialchars($product['name']); ?></td>

                        <!-- 🔥 CATEGORY DISPLAY -->
                        <td><?php echo htmlspecialchars($product['category'] ?? 'N/A'); ?></td>

                        <td>₱<?php echo number_format($product['price'], 2); ?></td>

                        <td><?php echo htmlspecialchars($product['description']); ?></td>

                        <td>
                            <a href="edit_product.php?id=<?php echo $id; ?>" class="btn btn-secondary btn-sm">Edit</a>
                            <a href="product_list.php?delete=<?php echo $id; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this product?')">Delete</a>
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