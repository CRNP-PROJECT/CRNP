<?php
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
    $imageFile = $_FILES['image'] ?? null;

    if ($name == "" || $price == "" || $description == "" || !$imageFile) {
        $message = "All fields are required.";
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="css/style.css">
<title>Add Product</title>
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
    <div class="container-sm">
        <div class="card">
            <h1 class="card-title mb-3">Add New Product</h1>

            <?php if($message): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <form action="" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label class="form-label">Product Name</label>
                    <input type="text" name="name" class="form-input" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Price</label>
                    <input type="number" step="0.01" name="price" class="form-input" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea" required></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Product Image</label>
                    <input type="file" name="image" class="form-input" accept="image/*" required>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Add Product</button>
            </form>
        </div>
    </div>
</div>

</body>
</html>
