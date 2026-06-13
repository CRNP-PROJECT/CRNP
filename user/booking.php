

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

<div class="booking-main-container">

    <h1 class="page-title-centered">BOOKING & RESERVATION</h1>

    <div class="booking-card">
        <form action="process.php" method="POST" enctype="multipart/form-data" class="booking-form" onsubmit="return validateBooking()">
            <input type="hidden" name="action" value="booking">

            <div class="booking-split-columns">
                
                <div class="booking-left-column">
                    <div class="form-group">
                        <label class="form-label">FULL NAME</label>
                        <input type="text" name="full_name" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">CONTACT NUMBER</label>
                        <input type="text" name="contact_number" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">ADDRESS</label>
                        <input type="text" name="address" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">DATE & TIME OF APPOINTMENT</label>
                        <input type="datetime-local" name="appointment_time" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">RETURN DATE & TIME OF ITEMS</label>
                        <input type="datetime-local" name="return_time" class="form-input" required>
                    </div>
                </div>

                <div class="booking-center-divider"></div>

                <div class="booking-right-column">
                    <h3 class="column-title-right">RENTAL ITEMS</h3>
                    
                    <div class="rental-items-scrollbox">
                        <?php if(empty($rent_items)): ?>
                            <p style="color: #ccc; text-align: center; margin-top: 20px;">No rental items available.</p>
                        <?php else: ?>
                            <?php foreach($rent_items as $id => $item): ?>
                                <?php if(!is_array($item)) continue; ?>
                                <div class="ui-item-row" style="display:flex; align-items:center; gap:15px; margin-bottom:15px;">
                                    <div class="ui-item-thumb">
                                        <?php if(!empty($item['image'])): ?>
                                            <img src="../admin/<?php echo htmlspecialchars($item['image']); ?>" style="width:70px; height:70px; object-fit:cover; border-radius:10px;">
                                        <?php else: ?>
                                            <div style="width:70px; height:70px; background:#ddd; border-radius:10px;"></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="ui-item-meta" style="flex:1;">
                                        <span class="ui-item-title" style="display:block; font-weight:bold; margin:0;">
                                            <?php echo htmlspecialchars($item['display_name'] ?? $item['name'] ?? 'Unnamed Item'); ?>
                                        </span>
                                        <small style="color:#111;">
                                            ₱<?php echo number_format($item['price'] ?? 0, 2); ?> | Available: <b><?php echo $item['quantity'] ?? 0; ?></b>
                                        </small>
                                    </div>
                                    <div class="ui-item-qty-selector">
                                        <input type="number" 
                                               name="rent_items[<?php echo $id; ?>]" 
                                               class="qty-input form-input" 
                                               min="0" 
                                               max="<?php echo $item['quantity'] ?? 0; ?>" 
                                               value="0" 
                                               style="width:80px;">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <h3 class="payment-section-heading">Payment Method</h3>
            
            <div class="booking-bottom-action-row">
                <div class="payment-pill-choices">
                    <label class="payment-pill-btn">
                        <input type="radio" name="payment_method" value="counter" checked onclick="togglePayment('counter')">
                        <span class="pill-custom-text">
                            <span class="radio-circle"></span>
                            OVER THE COUNTER
                        </span>
                    </label>

                    <label class="payment-pill-btn">
                        <input type="radio" name="payment_method" value="gcash" onclick="togglePayment('gcash')">
                        <span class="pill-custom-text">
                            <span class="radio-circle"></span>
                            <img src="../img/gcash.png" class="gcash-inline-icon" alt="GCash" style="width:20px; vertical-align:middle; margin-right:5px;">
                            GCASH
                        </span>
                    </label>
                </div>

                <div class="ui-total-display-pill">
                    <span class="total-label-text">TOTAL</span>
                    <span class="total-numeric-value">₱<span id="totalDisplay">0.00</span></span>
                </div>
            </div>

            <div id="gcashSection" style="display:none; margin: 20px auto; max-width: 500px;">
                <div class="gcash-container" style="background: rgba(255,255,255,0.1); padding: 20px; border-radius: 15px; text-align: center;">
                    <div class="gcash-image">
                        <p class="gcash-scan" style="color:#fff; margin-bottom: 10px;">Scan Me to Pay</p>
                        <img src="../img/qr.png" id="gcashQR" alt="GCash QR" style="width:140px; border-radius:10px; margin-bottom:10px;">
                        <p class="gcash-number" style="color:#fff; font-weight:bold; margin-bottom: 15px;">0912 345 6789</p>
                        <button type="button" class="gcash-download" onclick="downloadQR()" style="margin-bottom:15px; display:inline-block;">
                            Download QR
                        </button>
                    </div>
                    <div class="gcash-fields">
                        <input type="text" name="gcash_number" class="form-input" placeholder="Enter GCash Number" style="margin-bottom:10px;">
                        <input type="file" name="gcash_receipt" class="form-input" accept="image/*">
                    </div>
                </div>
            </div>

            <div class="action-submit-centering">
                <button type="submit" class="btn-booking-confirm">Confirm Booking</button>
            </div>

        </form>
    </div>
</div>

<script>
// GCASH TOGGLE
function togglePayment(type){
    document.getElementById("gcashSection").style.display =
        (type === "gcash") ? "block" : "none";
}

// PRICE MAP
const prices = <?php echo json_encode(
    is_array($rent_items)
    ? array_map(
        fn($i)=>floatval($i['price'] ?? 0),
        $rent_items
    )
    : []
); ?>;

// TOTAL
function updateTotal(){
    let total = 0;
    document.querySelectorAll(".qty-input").forEach(input=>{
        let match = input.name.match(/\[(.*?)\]/);
        if(!match) return;
        
        let id = match[1];
        let qty = parseInt(input.value)||0;
        
        if(prices[id]){
            total += prices[id] * qty;
        }
    });
    document.getElementById("totalDisplay").innerText = total.toFixed(2);
}

// VALIDATE
function validateBooking(){
    let hasItem = false;
    document.querySelectorAll(".qty-input").forEach(input=>{
        if(parseInt(input.value) > 0){
            hasItem = true;
        }
    });

    if(!hasItem){
        alert("Please select at least one item to rent.");
        return false;
    }

    let appointment = document.querySelector('[name="appointment_time"]').value;
    let returnTime = document.querySelector('[name="return_time"]').value;

    if(appointment && returnTime && new Date(returnTime) <= new Date(appointment)){
        alert("Return date must be after appointment date.");
        return false;
    }

    return true;
}

// EVENTS
document.querySelectorAll(".qty-input").forEach(input=>{
    input.addEventListener("input", updateTotal);
});
</script>
</body>
</html>