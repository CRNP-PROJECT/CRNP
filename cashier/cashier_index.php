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
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../styles.css">
<title>Cashier Dashboard</title>
</head>
<body class="cashier-dashboard">

<nav class="navbar">

    <div class="navbar-brand-container">
        <img src="../img/logo.png" class="logo" alt="Logo">
    </div>

    <ul class="navbar-menu">

        <li>
            <a href="cashier_index.php">
                <i class="fa-solid fa-chart-line"></i> Dashboard
            </a>
        </li>

        <li>
            <a href="create_order.php">
                <i class="fa-solid fa-bag-shopping"></i> New Order
            </a>
        </li>

        <li>
            <a href="view_orders.php">
                <i class="fa-solid fa-file-invoice"></i> Orders
            </a>
        </li>

        <li>
            <a href="view_bookings.php">
                <i class="fa-solid fa-calendar-days"></i> Bookings
            </a>
        </li>

        <li>
            <a href="cashier_logout.php">
                <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
            </a>
        </li>

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
            <div class="kpi-label">Walk-in and Online</div>
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