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

// GET RENT ITEMS
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

<header class="navbar">
    <div class="navbar-brand-container">
        <img src="../img/logo.png" class="logo">
    </div>
    <div class="navbar-right">
        <ul class="navbar-menu">
            <li><a href="index.php">Home</a></li>
            <li><a href="products.php">Products</a></li>
            <li><a href="booking.php" class="active">Booking</a></li>
            <li><a href="cart.php">Cart</a></li>
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

<!-- MAIN -->
<div class="booking-main-container">
<div class="booking-card">

<h1 class="page-title-centered">
    Booking &  Reservation
</h1>

<form action="process.php" method="POST" enctype="multipart/form-data" class="booking-form">

<input type="hidden" name="action" value="booking">

<!-- NAME -->
<div class="form-group">
<label class="form-label">Full Name</label>
<input type="text" name="full_name" class="form-input" required>
</div>

<!-- CONTACT -->
<div class="form-group">
<label class="form-label">Contact Number</label>
<input type="text" name="contact_number" class="form-input" required>
</div>

<!-- ADDRESS -->
<div class="form-group">
<label class="form-label">Address</label>
<input type="text" name="address" class="form-input" required>
</div>

<!-- DATE -->
<div class="form-group">
<label class="form-label">Date & Time</label>
<input type="datetime-local" name="appointment_time" class="form-input" required>
</div>

<!-- RENT ITEMS -->
<div class="booking-group">

<h3>Rental Items</h3>

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
                    <div style="width:60px;height:60px;background:#ddd;border-radius:10px;"></div>
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

<!-- TOTAL -->
<div class="booking-group" style="margin-top:20px; padding:15px; background:#f5f5f5; border-radius:10px;">
    <h3>Total</h3>
    <h2>₱ <span id="totalDisplay">0.00</span></h2>
</div>

<!-- PAYMENT -->
<div class="booking-group">

<h3>Payment Method</h3>

<label>
<input type="radio" name="payment_method" value="counter" checked onclick="togglePayment('counter')">
Over the Counter
</label>

<label>
<input type="radio" name="payment_method" value="gcash" onclick="togglePayment('gcash')">
<img src="../img/gcash.png" style="width:20px; vertical-align:middle; margin-right:5px;">
GCash
</label>

<div id="gcashSection">

    <div class="gcash-container">

        <!-- QR AREA -->
        <div class="gcash-image">
            <p class="gcash-scan">Scan Me to Pay</p>

            <img src="../img/qr.png" id="gcashQR" alt="GCash QR">

            <p class="gcash-number">0912 345 6789</p>

            <button type="button" class="gcash-download" onclick="downloadQR()">
                Download QR
            </button>
        </div>

        <!-- INPUTS -->
        <div class="gcash-fields">
            <input type="text" name="gcash_number" class="form-input" placeholder="Enter GCash Number">
            <input type="file" name="gcash_receipt" class="form-input" accept="image/*">
        </div>

    </div>

</div>

<!-- SUBMIT -->
<button type="submit" class="btn-booking-confirm">
Confirm Booking
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

// LIVE TOTAL
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