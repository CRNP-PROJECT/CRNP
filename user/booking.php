<?php 
include(__DIR__ . "/../config.php"); 
include(__DIR__ . "/../firebaseRDB.php"); 

if(!isset($_SESSION['email'])){
    header("Location: login.php");
    exit;
} 
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<!-- Your CSS -->
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
        <li><a href="booking.php"><i class="fa-solid fa-calendar-check"></i> Booking</a></li>
        <li><a href="cart.php"><i class="fa-solid fa-cart-shopping"></i> Cart</a></li>
        <li><a href="../logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
    </ul>
</nav>

<!-- MAIN CONTAINER -->
<div class="booking-main-container">

    <!-- CARD -->
    <div class="booking-card">

        <!-- TITLE -->
        <h1 class="page-title-centered">
            <i class="fa-solid fa-file-signature"></i> Booking / Reservation
        </h1>

        <!-- FORM -->
        <form action="process.php" method="POST" class="booking-form">

            <!-- hidden action -->
            <input type="hidden" name="action" value="booking">

            <!-- FULL NAME -->
            <div class="form-group">
                <label class="form-label">
                    <i class="fa-solid fa-user"></i> Full Name
                </label>
                <input type="text" name="full_name" class="form-input" required>
            </div>

            <!-- CONTACT -->
            <div class="form-group">
                <label class="form-label">
                    <i class="fa-solid fa-phone"></i> Contact Number
                </label>
                <input type="text" name="contact_number" class="form-input" required>
            </div>

            <!-- ADDRESS -->
            <div class="form-group">
                <label class="form-label">
                    <i class="fa-solid fa-location-dot"></i> Address
                </label>
                <input type="text" name="address" class="form-input" required>
            </div>

            <!-- DATE TIME -->
            <div class="form-group">
                <label class="form-label">
                    <i class="fa-solid fa-clock"></i> Date & Time
                </label>
                <input type="date" name="appointment_time" class="form-input" required>
            </div>

            <!-- TABLES & CHAIRS ROW -->
            <div class="booking-row">

                <!-- TABLES -->
                <div class="booking-group">
                    <h3 class="booking-group-title">
                        <i class="fa-solid fa-table"></i> Tables
                    </h3>
                    <input type="number" name="tables_qty" class="form-input" min="0" value="0">
                </div>

                <!-- CHAIRS -->
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

                <!-- dynamic container -->
                <div id="skirting-container">

                    <!-- FIRST ROW -->
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

                <!-- ADD BUTTON -->
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

    /* 🔥 FIXED: used backticks so it works */
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