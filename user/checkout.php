<?php
session_start();
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

<title>Checkout | CRNP</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">

<link rel="stylesheet" href="../styles.css">
<link rel="stylesheet" href="checkout_styles.css">
</head>

<body class="checkout-page">

<!-- NAVBAR (UNCHANGED) -->
<nav class="navbar">
    <div class="navbar-brand-container">
        <img src="../img/logo.png" class="logo"> 
    </div>

    <ul class="navbar-menu">
        <li><a href="index.php"><i class="fa-solid fa-house"></i> Home</a></li>
        <li><a href="products.php"><i class="fa-solid fa-shop"></i> Products</a></li>
        <li><a href="cart.php"><i class="fa-solid fa-cart-shopping"></i> Cart</a></li>
        <li><a href="../logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
    </ul>
</nav>

<!-- TOP BAR (UNCHANGED) -->
<div class="checkout-topbar">
    <div class="checkout-back-wrapper">
        <a href="cart.php" class="btn-back-nav">
            <i class="fa-solid fa-arrow-left"></i> Back
        </a>
    </div>

    <h1 class="page-title">
        <i class="fa-solid fa-credit-card"></i> Checkout & Reservation
    </h1>
</div>

<div class="container">
<div class="checkout-grid">

    <!-- ORDER SUMMARY (UNCHANGED) -->
    <div class="card summary-card">
        <h3 class="card-title"><i class="fa-solid fa-list-check"></i> Order Summary</h3>

        <div class="summary-list">
            <?php foreach($cart_items as $item): ?>
            <div class="summary-item">
                <div>
                    <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                    <span class="qty">x<?php echo $item['qty']; ?></span>
                </div>
                <div>₱<?php echo number_format($item['subtotal'], 2); ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="total-section">
            <span>Total</span>
            <span class="grand-total">₱<?php echo number_format($total, 2); ?></span>
        </div>
    </div>

    <!-- FORM (ONLY ADDITION INSIDE GCASH SECTION) -->
    <div class="card form-card">

        <h3 class="card-title"><i class="fa-solid fa-pen-to-square"></i> Reservation Details</h3>

        <form action="process.php" method="POST" enctype="multipart/form-data">

            <input type="hidden" name="action" value="confirm_checkout">

            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="full_name" class="form-input" required>
            </div>

            <div class="form-group">
                <label>Contact Number</label>
                <input type="text" name="contact_number" class="form-input" required>
            </div>

            <div class="form-group">
                <label>Guest Count</label>
                <input type="number" name="num_people" class="form-input" required>
            </div>

            <div class="form-group">
                <label>Date & Time</label>
                <input type="datetime-local" name="appointment_time" class="form-input" required>
            </div>

            <!-- PAYMENT -->
            <div class="form-group">
                <label>Payment Method</label>

                <label>
                    <input type="radio" name="payment_method" value="counter" checked onclick="togglePayment('counter')">
                    Over the Counter
                </label>

                <label>
                    <input type="radio" name="payment_method" value="gcash" onclick="togglePayment('gcash')">
                    GCash
                </label>
            </div>

            <!-- GCASH SECTION (ONLY CHANGE = ADD FIELD) -->
            <div id="gcashSection" style="display:none; margin-top:10px;">

                <div style="text-align:center; margin-bottom:10px;">
                    <img src="../img/2.jpg" style="max-width:200px;">
                </div>

                <label>GCash Number</label>
                <input type="text" name="gcash_number" class="form-input" placeholder="Enter GCash Number">

                <label>Upload Receipt</label>
                <input type="file" name="gcash_receipt" class="form-input" accept="image/*">

            </div>

            <button type="submit" class="btn-confirm">
                Confirm & Reserve
            </button>

        </form>
    </div>

</div>
</div>

<script>
function togglePayment(type){
    document.getElementById("gcashSection").style.display =
        (type === "gcash") ? "block" : "none";
}
</script>

</body>
</html>