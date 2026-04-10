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
<link rel="stylesheet" href="css/style.css">
<title>Edit Product</title>
</head>
<body>

<nav class="navbar">
    <a href="admin_index.php" class="navbar-brand">Admin Dashboard</a>
    <ul class="navbar-menu">
        <li><a href="product_list.php">Product List</a></li>
        <li><a href="admin_log.php">Logout</a></li>
    </ul>
</nav>

<div class="container">
    <div class="container-sm">
        <div class="card">

            <h1 class="card-title mb-3">Edit Product</h1>

            <form method="POST">

                <!-- NAME -->
                <div class="form-group">
                    <label class="form-label">Product Name</label>
                    <input type="text" name="name"
                        value="<?php echo htmlspecialchars($product['name']); ?>"
                        class="form-input" required>
                </div>

                <!-- PRICE -->
                <div class="form-group">
                    <label class="form-label">Price</label>
                    <input type="number" step="0.01" name="price"
                        value="<?php echo htmlspecialchars($product['price']); ?>"
                        class="form-input" required>
                </div>

                <!-- CATEGORY 🔥 NEW -->
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-input" required>
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
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea" required>
<?php echo htmlspecialchars($product['description']); ?>
                    </textarea>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    Update Product
                </button>

            </form>

        </div>
    </div>
</div>

</body>
</html>