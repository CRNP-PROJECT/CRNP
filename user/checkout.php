<?php
include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

if(!isset($_SESSION['email'])){
    header("Location: login.php");
    exit;
}

$rdb = new firebaseRDB($databaseURL);
$cart = $_SESSION['cart'] ?? [];

if(empty($cart)){
    header("Location: cart.php");
    exit;
}

$cart_items = [];
$total = 0;
foreach($cart as $id => $qty){
    $product = json_decode($rdb->retrieve("/products/$id"), true);
    if(!$product) continue;

    $product['qty'] = $qty;
    $product['subtotal'] = floatval($product['price']) * $qty;
    $total += $product['subtotal'];
    $cart_items[$id] = $product;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="checkout_styles.css"> 
    <title>Checkout | CRNP</title>
</head>
<body class="checkout-page">

<nav class="navbar">
    <div class="navbar-brand-container">
    <img src="../img/logo.png" alt="Logo" class="logo"> 
    <a href="cart.php" class="btn-back-nav">
        <i class="fa-solid fa-arrow-left-long"></i> Back to Cart
    </a>
</div>
    <ul class="navbar-menu">
        <li><a href="index.php"><i class="fa-solid fa-house"></i> Home</a></li>
        <li><a href="products.php"><i class="fa-solid fa-shop"></i> Products</a></li>
        <li><a href="cart.php"><i class="fa-solid fa-cart-shopping"></i> Cart</a></li>
        <li><a href="../logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
    </ul>
</nav>

<header class="page-header-centered">
    <h1 class="page-title">
        <i class="fa-solid fa-credit-card"></i> Checkout & Reservation
    </h1>
</header>

<div class="container">
    <div class="checkout-grid">
        <div class="card summary-card">
            <h3 class="card-title"><i class="fa-solid fa-list-check"></i> Order Summary</h3>
            <div class="summary-list">
                <?php foreach($cart_items as $id => $item): ?>
                <div class="summary-item">
                    <div class="item-info">
                        <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                        <span class="qty-pill">x<?php echo $item['qty']; ?></span>
                    </div>
                    <span class="item-subtotal">₱<?php echo number_format($item['subtotal'], 2); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="total-section">
                <span>Grand Total</span>
                <span class="grand-total">₱<?php echo number_format($total, 2); ?></span>
            </div>
        </div>

        <div class="card form-card">
            <h3 class="card-title"><i class="fa-solid fa-pen-to-square"></i> Reservation Details</h3>
            <form action="process.php" method="POST" class="checkout-form">
                <input type="hidden" name="action" value="confirm_checkout">

                <div class="form-group">
                    <label class="form-label">
                        <i class="fa-solid fa-user"></i> Full Name
                    </label>
                    <div class="input-container">
                        <input type="text" name="full_name" class="form-input" placeholder="Enter your name" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <i class="fa-solid fa-phone"></i> Contact Number
                    </label>
                    <div class="input-container">
                        <input type="text" name="contact_number" class="form-input" placeholder="09XXXXXXXXX" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <i class="fa-solid fa-users"></i> Guest Count
                    </label>
                    <div class="input-container">
                        <input type="number" name="num_people" class="form-input" min="1" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <i class="fa-solid fa-calendar-day"></i> Date & Time
                    </label>
                    <div class="input-container">
                        <input type="datetime-local" name="appointment_time" class="form-input" required>
                    </div>
                </div>

                <button type="submit" class="btn-confirm">
                    Confirm & Reserve <i class=" "></i>
                </button>
            </form>
        </div>
    </div>
</div>

</body>
</html>