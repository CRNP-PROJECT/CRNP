<?php
include(__DIR__ . "/../config.php");

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
<title>Booking Success</title>
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="navbar-brand">CRNP</a>
    <ul class="navbar-menu">
        <li><a href="index.php">Home</a></li>
        <li><a href="products.php">Products</a></li>
    </ul>
</nav>

<div class="container" style="padding-top: 80px;">
    <div class="container-sm">
        <div class="card text-center">
            <div style="font-size: 48px; margin-bottom: 16px;">✓</div>
            <h1 class="auth-title" style="color: var(--success);">Booking Confirmed!</h1>
            <p class="text-muted mt-2">Thank you for your reservation. Your booking has been successfully recorded.</p>
            <p class="text-muted">We will contact you if needed at your registered contact number.</p>
            <a href="index.php" class="btn btn-primary mt-3">Back to Home</a>
        </div>
    </div>
</div>

</body>
</html>
