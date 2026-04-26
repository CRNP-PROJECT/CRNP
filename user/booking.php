<?php
session_start();

include(__DIR__ . "/../config.php"); 
include(__DIR__ . "/../firebaseRDB.php"); 

if(!isset($_SESSION['email'])){
    header("Location: login.php");
    exit;
} 

$username = $_SESSION['username'] ?? "User";
$email = $_SESSION['email'];

$rdb = new firebaseRDB($databaseURL);

// 🔥 GET RENT ITEMS FROM FIREBASE
$items_raw = $rdb->retrieve("/rent_items");
$rent_items = json_decode($items_raw, true);

if(!is_array($rent_items)){
    $rent_items = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="../styles.css">

<title>Booking / Reservation</title>
</head>

<body class="booking-page-body">

<!-- NAVBAR -->
<nav class="navbar">
    <div class="navbar-brand-container">
        <img src="../img/logo.png" alt="Logo" class="logo">
    </div>

    <ul class="navbar-menu">

        <li><a href="index.php"><i class="fa-solid fa-house"></i> Home</a></li>
        <li><a href="products.php"><i class="fa-solid fa-shop"></i> Products</a></li>
        <li><a href="booking.php" class="active"><i class="fa-solid fa-calendar-check"></i> Booking</a></li>
        <li><a href="cart.php"><i class="fa-solid fa-cart-shopping"></i> Cart</a></li>
        <li><a href="your_orders.php"><i class="fa-solid fa-box-open"></i> Your Order</a></li>
        <li><a href="aboutus.php"><i class="fa-solid fa-circle-info"></i> About Us</a></li>

        <li class="navbar-dropdown">
            <a href="#">
                <i class="fa-solid fa-user"></i>
                <?php echo htmlspecialchars($username); ?> ▼
            </a>

            <div class="navbar-dropdown-content">
                <a href="your_profile.php"><i class="fa-solid fa-id-card"></i> My Profile</a>
                <a href="your_orders.php"><i class="fa-solid fa-box"></i> Your Orders</a>
                <a href="../logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </div>
        </li>

    </ul>
</nav>

<!-- MAIN -->
<div class="booking-main-container">

<div class="booking-card">

<h1 class="page-title-centered">
    <i class="fa-solid fa-file-signature"></i> Booking / Reservation
</h1>

<form action="process.php" method="POST" enctype="multipart/form-data" class="booking-form">

<input type="hidden" name="action" value="booking">

<!-- NAME -->
<div class="form-group">
<label class="form-label"><i class="fa-solid fa-user"></i> Full Name</label>
<input type="text" name="full_name" class="form-input" required>
</div>

<!-- CONTACT -->
<div class="form-group">
<label class="form-label"><i class="fa-solid fa-phone"></i> Contact Number</label>
<input type="text" name="contact_number" class="form-input" required>
</div>

<!-- ADDRESS -->
<div class="form-group">
<label class="form-label"><i class="fa-solid fa-location-dot"></i> Address</label>
<input type="text" name="address" class="form-input" required>
</div>

<!-- DATE -->
<div class="form-group">
<label class="form-label"><i class="fa-solid fa-clock"></i> Date & Time</label>
<input type="datetime-local" name="appointment_time" class="form-input" required>
</div>

<!-- 🔥 RENT ITEMS -->
<div class="booking-group">

<h3><i class="fa-solid fa-box"></i> Rental Items</h3>

<?php if(empty($rent_items)): ?>
    <p>No rental items available.</p>
<?php else: ?>

    <?php foreach($rent_items as $id => $item): ?>
        
        <?php if(!is_array($item)) continue; ?>

        <div class="booking-row" style="display:flex; align-items:center; gap:15px; margin-bottom:10px;">

            <!-- IMAGE -->
            <div>
                <?php if(!empty($item['image'])): ?>
                    <img src="../admin/<?php echo htmlspecialchars($item['image']); ?>" 
                         style="width:60px;height:60px;object-fit:cover;border-radius:10px;">
                <?php else: ?>
                    <div style="width:60px;height:60px;background:#ddd;border-radius:10px;display:flex;align-items:center;justify-content:center;">
                        <i class="fa-solid fa-image"></i>
                    </div>
                <?php endif; ?>
            </div>

            <!-- NAME -->
            <div style="flex:1;">
                <h4 style="margin:0;">
                    <?php echo htmlspecialchars($item['display_name'] ?? $item['name']); ?>
                </h4>
            </div>

            <!-- QTY -->
            <div>
                <input 
                    type="number" 
                    name="rent_items[<?php echo $id; ?>]" 
                    class="form-input qty-input"
                    min="0" 
                    value="0"
                    style="width:80px;"
                >
            </div>

        </div>

    <?php endforeach; ?>

<?php endif; ?>

</div>

<!-- 🔥 TOTAL -->
<div class="booking-group" style="margin-top:20px; padding:15px; background:#f5f5f5; border-radius:10px;">
    <h3><i class="fa-solid fa-receipt"></i> Total</h3>
    <h2>₱ <span id="totalDisplay">0.00</span></h2>
</div>

<!-- PAYMENT -->
<div class="booking-group">

<h3><i class="fa-solid fa-money-bill"></i> Payment Method</h3>

<label>
<input type="radio" name="payment_method" value="counter" checked onclick="togglePayment('counter')">
Over the Counter
</label>

<label>
<input type="radio" name="payment_method" value="gcash" onclick="togglePayment('gcash')">
GCash
</label>

<div id="gcashSection" style="display:none; margin-top:10px;">

    <div style="text-align:center;">
        <img src="../img/2.jpg" style="max-width:200px;">
    </div>

    <label>GCash Number</label>
    <input type="text" name="gcash_number" class="form-input">

    <label>Upload Receipt</label>
    <input type="file" name="gcash_receipt" class="form-input" accept="image/*">

</div>

</div>

<!-- SUBMIT -->
<button type="submit" class="btn-booking-confirm">
<i class="fa-solid fa-check"></i> Confirm Booking
</button>

</form>

</div>
</div>

<!-- SCRIPT -->
<script>
function togglePayment(type){
    document.getElementById("gcashSection").style.display =
        (type === "gcash") ? "block" : "none";
}

// 🔥 LIVE TOTAL CALCULATION
const prices = <?php echo json_encode(array_map(fn($i) => floatval($i['price'] ?? 0), $rent_items)); ?>;

function updateTotal() {
    let total = 0;

    document.querySelectorAll(".qty-input").forEach(input => {
        let id = input.name.match(/\[(.*?)\]/)[1];
        let qty = parseInt(input.value) || 0;

        if (prices[id]) {
            total += prices[id] * qty;
        }
    });

    document.getElementById("totalDisplay").innerText = total.toFixed(2);
}

document.querySelectorAll(".qty-input").forEach(input => {
    input.addEventListener("input", updateTotal);
});
</script>

</body>
</html>