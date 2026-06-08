<?php
session_start();
include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

if(!isset($_SESSION['email'])){
    header("Location: login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'User';

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
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../styles.css">
<link rel="stylesheet" href="checkout_styles.css">

</head>

<body class="checkout-page">

<!-- ✅ NAVBAR -->
<header class="navbar">
    <div class="navbar-brand-container">
        <img src="../img/logo.png" class="logo">
    </div>

    <div class="navbar-right">
        <ul class="navbar-menu">
            <li><a href="index.php">Home</a></li>
            <li><a href="products.php">Products</a></li>
            <li><a href="booking.php">Booking</a></li>
            <li><a href="cart.php" class="active">Cart</a></li>
            <li><a href="your_orders.php">Orders</a></li>
            <li><a href="aboutus.php">About</a></li>
        </ul>

        <div class="navbar-dropdown">
            <span class="navbar-user-btn">
                <?php echo htmlspecialchars($username); ?> ▼
            </span>

            <div class="navbar-dropdown-content">
                <a href="your_profile.php">My Profile</a>
                <a href="your_orders.php">Your Orders</a>
                <a href="../logout.php">Logout</a>
            </div>
        </div>
    </div>
</header>

<!-- ✅ HEADER -->
<div class="checkout-header">
    <a href="cart.php" class="checkout-back"><span class="btn-arrow">&larr;</span>BACK</a>
    <h1>CHECKOUT & RESERVATION</h1>
</div>

<div class="checkout-container">
<div class="checkout-grid">

    <div class="checkout-card checkout-summary">

        <h2 class="checkout-title">ORDER SUMMARY</h2>

        <div class="checkout-items">
            <?php foreach($cart_items as $item): ?>
            <div class="checkout-item">
                <div>
                    <strong><?= htmlspecialchars($item['name']); ?></strong>
                    <span>x<?= $item['qty']; ?></span>
                </div>
                <div>₱<?= number_format($item['subtotal'],2); ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="checkout-total">
            TOTAL: ₱<?= number_format($total,2); ?>
        </div>

    </div>

    <div class="checkout-card checkout-form">

        <h2 class="checkout-title">RESERVATION DETAILS</h2>

        <form action="process.php" method="POST" enctype="multipart/form-data">

                <input type="hidden" name="action" value="confirm_checkout">

           <div class="checkout-row full">
                <input type="text" name="full_name" placeholder="Full Name" required>
            </div>

            <div class="checkout-row two">
                <input type="text" name="contact_number" placeholder="Contact Number" required>
                <input type="number" name="num_people" placeholder="Guest Count" required>
            </div>

          <div class="checkout-row full">
                <input type="datetime-local" name="appointment_time" required>
                </div>

            <div class="checkout-payment">

                <label class="checkout-pay-option">
                    <input type="radio" name="payment_method" value="counter" checked onclick="togglePayment('counter')">
                    Over the Counter
                </label>

                <label class="checkout-pay-option checkout-gcash-option">
                    <input type="radio" name="payment_method" value="gcash" onclick="togglePayment('gcash')">
                    <img src="../img/gcash.png" alt="GCash">
                    GCash
                </label>

            </div>

            <div id="gcashBox" class="checkout-gcash">

                <div class="checkout-gcash-left">
                    <p class="checkout-gcash-text">Scan to Pay</p>

                    <img src="../img/qr.png" alt="QR Code">

                    <span class="checkout-gcash-number">0912 345 6789</span>

                    <button type="button" class="checkout-download" onclick="downloadQR()">
                        Download QR
                    </button>
                </div>

                <div class="checkout-gcash-right">
                    <input type="text" name="gcash_number" placeholder="Enter GCash Number">
                    <input type="file" name="gcash_receipt" accept="image/*">
                </div>

            </div>

            <button type="submit" class="checkout-btn">
                CONFIRM & RESERVE
            </button>

        </form>

    </div>

</div>
</div>

<script>
function togglePayment(type){
    document.getElementById("gcashBox").style.display =
        (type === "gcash") ? "flex" : "none";
}

function downloadQR(){
    const link = document.createElement('a');
    link.href = "../img/qr.png";
    link.download = "gcash-qr.png";
    link.click();
}
</script>

</body>
</html>