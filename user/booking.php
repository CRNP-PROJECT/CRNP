<?php

include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

if(!isset($_SESSION['email'])){
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../styles.css">
<title>Booking / Reservation</title>
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="navbar-brand">CRNP</a>
    <ul class="navbar-menu">
        <li><a href="index.php">Home</a></li>
        <li><a href="products.php">Products</a></li>
        <li><a href="booking.php">Booking</a></li>
        <li><a href="../logout.php">Logout</a></li>
    </ul>
</nav>

<div class="container">
    <div class="container-sm">
        <div class="page-header">
            <h1 class="page-title">Booking / Reservation</h1>
        </div>

        <div class="card">
            <form action="process.php" method="POST">
                <input type="hidden" name="action" value="booking">

                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-input" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Contact Number</label>
                    <input type="text" name="contact_number" class="form-input" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Address</label>
                    <input type="text" name="address" class="form-input" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Reservation Date & Time</label>
                    <input type="datetime-local" name="appointment_time" class="form-input" required>
                </div>

                <div class="booking-group">
                    <h3 class="booking-group-title">Tables</h3>
                    <div class="form-group mb-0">
                        <label class="form-label">Quantity</label>
                        <input type="number" name="tables_qty" class="form-input" min="0" value="0">
                    </div>
                </div>

                <div class="booking-group">
                    <h3 class="booking-group-title">Chairs</h3>
                    <div class="form-group mb-0">
                        <label class="form-label">Quantity</label>
                        <input type="number" name="chairs_qty" class="form-input" min="0" value="0">
                    </div>
                </div>

                <div class="booking-group">
                    <h3 class="booking-group-title">Skirting Cloth</h3>
                    <div id="skirting-container">
                        <div class="skirting-row">
                            <div class="form-group">
                                <label class="form-label">Color</label>
                                <select name="skirting_color[]" class="form-select">
                                    <option value="">Select Color</option>
                                    <option value="White">White</option>
                                    <option value="Red">Red</option>
                                    <option value="Blue">Blue</option>
                                    <option value="Pink">Pink</option>
                                </select>
                            </div>
                            <div class="form-group mb-0">
                                <label class="form-label">Quantity</label>
                                <input type="number" name="skirting_qty[]" class="form-input" min="0" value="0">
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm mt-2" onclick="addSkirting()">+ Add Another Color</button>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Confirm Booking</button>
            </form>
        </div>
    </div>
</div>

<script>
function addSkirting() {
    const container = document.getElementById('skirting-container');
    const div = document.createElement('div');
    div.classList.add('skirting-row');
    div.style.marginTop = '16px';
    div.style.paddingTop = '16px';
    div.style.borderTop = '1px dashed var(--border)';
    div.innerHTML = `
        <div class="form-group">
            <label class="form-label">Color</label>
            <select name="skirting_color[]" class="form-select">
                <option value="">Select Color</option>
                <option value="White">White</option>
                <option value="Red">Red</option>
                <option value="Blue">Blue</option>
                <option value="Pink">Pink</option>
            </select>
        </div>
        <div class="form-group mb-0">
            <label class="form-label">Quantity</label>
            <input type="number" name="skirting_qty[]" class="form-input" min="0" value="0">
        </div>
    `;
    container.appendChild(div);
}
</script>

</body>
</html>
