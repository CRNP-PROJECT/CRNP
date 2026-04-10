<?php
session_start();
if(!isset($_SESSION['cashier_email'])){
    header("Location: cashier_login.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../styles.css">
<title>Cashier Dashboard</title>
</head>
<body>

<nav class="navbar">
    <a href="cashier_index.php" class="navbar-brand">Cashier Dashboard</a>
    <ul class="navbar-menu">
        <li><a href="create_order.php">New Order</a></li> <!-- this is for ordering when there is a customer that usually walking in in crnp -->
        <li><a href="view_orders.php">View Orders</a></li>
        <li><a href="view_bookings.php">View Bookings</a></li>
        <li><a href="cashier_logout.php">Logout</a></li>
    </ul>
</nav>

<div class="container">
    <div class="card">
        <h1 class="card-title">Welcome, <?php echo htmlspecialchars($_SESSION['cashier_email']); ?>!</h1>
        <p class="text-muted mt-1">Manage orders and bookings</p>
    </div>

    <div class="kpi-grid mt-3">

        <!--  NEW WALK-IN ORDER FOR CRNP  -->
        <a href="create_order.php" class="card" style="text-decoration: none;">
            <div class="kpi-value">New Order</div>
            <div class="kpi-label">Walk-in Customer</div>
        </a>

        
        <a href="view_orders.php" class="card" style="text-decoration: none;">
            <div class="kpi-value">Orders</div>
            <div class="kpi-label">View & Manage</div>
        </a>

        <a href="view_bookings.php" class="card" style="text-decoration: none;">
            <div class="kpi-value">Bookings</div>
            <div class="kpi-label">View Reservations</div>
        </a>

    </div>
</div>

</body>
</html>