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

<<<<<<< HEAD
<title>Checkout | CRNP</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">

<link rel="stylesheet" href="../styles.css">
<link rel="stylesheet" href="checkout_styles.css">
=======
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">

<link rel="stylesheet" href="../styles.css">
<link rel="stylesheet" href="checkout_styles.css"> 

<title>Checkout | CRNP</title>
>>>>>>> 784d58b7356ff90b699f7f25dfe2dd02149d3401
</head>

<body class="checkout-page">

<!-- NAVBAR -->
<nav class="navbar">
    <div class="navbar-brand-container">
<<<<<<< HEAD
        <img src="../img/logo.png" class="logo"> 
=======
        <img src="../img/logo.png" alt="Logo" class="logo"> 
        <a href="cart.php" class="btn-back-nav">
            <i class="fa-solid fa-arrow-left-long"></i> Back to Cart
        </a>
>>>>>>> 784d58b7356ff90b699f7f25dfe2dd02149d3401
    </div>

    <ul class="navbar-menu">
        <li><a href="index.php"><i class="fa-solid fa-house"></i> Home</a></li>
        <li><a href="products.php"><i class="fa-solid fa-shop"></i> Products</a></li>
        <li><a href="cart.php"><i class="fa-solid fa-cart-shopping"></i> Cart</a></li>
        <li><a href="../logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
    </ul>
</nav>

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
<<<<<<< HEAD
    <div class="checkout-grid">

        <!-- ORDER SUMMARY -->
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
=======
<div class="checkout-grid">

    <!-- ================= ORDER SUMMARY ================= -->
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
>>>>>>> 784d58b7356ff90b699f7f25dfe2dd02149d3401
            </div>
            <?php endforeach; ?>
        </div>

<<<<<<< HEAD
        <!-- FORM -->
        <div class="card form-card">
            <h3 class="card-title"><i class="fa-solid fa-pen"></i> Reservation Details</h3>

            <form action="process.php" method="POST">
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
                    <input type="number" name="num_people" class="form-input" min="1" required>
                </div>

                <div class="form-group">
                    <label>Date & Time</label>
                    <input type="datetime-local" name="appointment_time" class="form-input" required>
                </div>

                <button type="submit" class="btn-confirm">
                    Confirm & Reserve
                </button>
            </form>
=======
        <div class="total-section">
            <span>Grand Total</span>
            <span class="grand-total">₱<?php echo number_format($total, 2); ?></span>
>>>>>>> 784d58b7356ff90b699f7f25dfe2dd02149d3401
        </div>

    </div>

    <!-- ================= FORM ================= -->
    <div class="card form-card">
        <h3 class="card-title"><i class="fa-solid fa-pen-to-square"></i> Reservation Details</h3>

        <form action="process.php" method="POST" class="checkout-form">
            <input type="hidden" name="action" value="confirm_checkout">

            <!-- NAME -->
            <div class="form-group">
                <label class="form-label"><i class="fa-solid fa-user"></i> Full Name</label>
                <div class="input-container">
                    <input type="text" name="full_name" class="form-input" required>
                </div>
            </div>

            <!-- CONTACT -->
            <div class="form-group">
                <label class="form-label"><i class="fa-solid fa-phone"></i> Contact Number</label>
                <div class="input-container">
                    <input type="text" name="contact_number" class="form-input" required>
                </div>
            </div>

            <!-- GUEST -->
            <div class="form-group">
                <label class="form-label"><i class="fa-solid fa-users"></i> Guest Count</label>
                <div class="input-container">
                    <input type="number" name="num_people" class="form-input" min="1" required>
                </div>
            </div>

            <!-- DATE -->
            <div class="form-group">
                <label class="form-label"><i class="fa-solid fa-calendar-day"></i> Date & Time</label>
                <div class="input-container">
                    <input type="datetime-local" name="appointment_time" class="form-input" required>
                </div>
            </div>

            <!-- PAYMENT METHOD -->
            <div class="form-group">
                <label class="form-label"><i class="fa-solid fa-money-bill"></i> Payment Method</label>

                <div class="payment-options">
                    <label>
                        <input type="radio" name="payment_method" value="counter" checked onclick="togglePayment('counter')">
                        Over the Counter
                    </label>

                    <label>
                        <input type="radio" name="payment_method" value="gcash" onclick="togglePayment('gcash')">
                        GCash
                    </label>
                </div>
            </div>

            <!-- GCASH QR + NUMBER -->
            <div class="form-group" id="gcashSection" style="display:none;">
                
                <div style="text-align:center; margin-bottom:10px;">
                    <img src="../img/2.jpg" alt="GCash QR" style="max-width:200px;">
                </div>

                <label class="form-label"><i class="fa-solid fa-mobile-screen"></i> GCash Number</label>
               
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
    const gcash = document.getElementById("gcashSection");

    if(type === "gcash"){
        gcash.style.display = "block";
    } else {
        gcash.style.display = "none";
    }
}
</script>

</body>
</html>