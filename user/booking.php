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
        <a href="index.php" class="navbar-brand"></a>
    </div>

    <ul class="navbar-menu">

        <li><a href="index.php"><i class="fa-solid fa-house"></i> Home</a></li>

        <li><a href="products.php"><i class="fa-solid fa-shop"></i> Products</a></li>

        <li><a href="booking.php" class="active"><i class="fa-solid fa-calendar-check"></i> Booking</a></li>

        <li><a href="cart.php"><i class="fa-solid fa-cart-shopping"></i> Cart</a></li>

        <li><a href="your_orders.php"><i class="fa-solid fa-box-open"></i> Your Order</a></li>

        <li><a href="aboutus.php"><i class="fa-solid fa-circle-info"></i> About Us</a></li>

        <!-- USER DROPDOWN -->
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

<!-- MAIN CONTAINER -->
<div class="booking-main-container">

    <div class="booking-card">

        <h1 class="page-title-centered">
            <i class="fa-solid fa-file-signature"></i> Booking / Reservation
        </h1>

        <form action="process.php" method="POST" class="booking-form">

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

            <!-- TABLES & CHAIRS -->
            <div class="booking-row">

                <div class="booking-group">
                    <h3 class="booking-group-title">
                        <i class="fa-solid fa-table"></i> Tables
                    </h3>
                    <input type="number" name="tables_qty" class="form-input" min="0" value="0">
                </div>

                <div class="booking-group">
                    <h3 class="booking-group-title">
                        <i class="fa-solid fa-chair"></i> Chairs
                    </h3>
                    <input type="number" name="chairs_qty" class="form-input" min="0" value="0">
                </div>

            </div>

            <!-- SKIRTING -->
            <div class="booking-group">

                <h3 class="booking-group-title">
                    <i class="fa-solid fa-palette"></i> Skirting Cloth
                </h3>

                <div id="skirting-container">

                    <div class="skirting-flex-row">
                        <select name="skirting_color[]" class="form-select">
                            <option value="">Color</option>
                            <option value="White">White</option>
                            <option value="Red">Red</option>
                            <option value="Blue">Blue</option>
                            <option value="Pink">Pink</option>
                        </select>

                        <input type="number" name="skirting_qty[]" 
                               class="form-input qty-input" 
                               min="0" value="0">
                    </div>

                </div>

                <button type="button" class="btn-add-skirting" onclick="addSkirting()">
                    <i class="fa-solid fa-plus"></i> Add Color
                </button>

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
function addSkirting() {
    const container = document.getElementById('skirting-container');

    const div = document.createElement('div');
    div.className = 'skirting-flex-row extra-row';

    div.innerHTML = `
        <select name="skirting_color[]" class="form-select">
            <option value="">Color</option>
            <option value="White">White</option>
            <option value="Red">Red</option>
            <option value="Blue">Blue</option>
            <option value="Pink">Pink</option>
        </select>

        <input type="number" name="skirting_qty[]" 
               class="form-input qty-input" 
               min="0" value="0">
    `;

    container.appendChild(div);
}
</script>

</body>
</html>