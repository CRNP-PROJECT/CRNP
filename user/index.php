<?php
session_start();
include("../config.php");
include("../firebaseRDB.php"); // Included to fetch product list for suggestions

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit;
}

$username = $_SESSION['username'] ?? "User";

// Fetch product names for the suggestion dropdown
$rdb = new firebaseRDB($databaseURL);
$retrieve = $rdb->retrieve("/products");
$data = json_decode($retrieve, true);
$productNames = [];
if(is_array($data)) {
    foreach($data as $product) {
        $productNames[] = $product['name'] ?? '';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Crates N' Plates</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../styles.css">
</head>

<body class="navbar-body dashboard-body">

<header class="navbar">
    <div class="navbar-brand-container"><img src="../img/logo.png" class="logo"></div>
    <div class="navbar-right">
        <ul class="navbar-menu">
            <li><a href="index.php" class="active">Home</a></li>
            <li><a href="products.php">Products</a></li>
            <li><a href="booking.php">Booking</a></li>
            <li><a href="cart.php">Cart</a></li>
            <li><a href="aboutus.php">About</a></li>
        </ul>

        <form action="products.php" method="GET" class="search-box" style="position: relative;">
            <button type="submit" style="background:none; border:none; cursor:pointer; color:inherit;">
                <i class="fa-solid fa-magnifying-glass"></i>
            </button>
            <input type="text" name="search" placeholder="Search..." class="navbar-search" autocomplete="off">
            <div id="suggestion-box"></div>
        </form>

        <div class="navbar-dropdown">
            <span class="navbar-user-btn"><i class="fa-regular fa-user"></i> <?php echo htmlspecialchars($username); ?></span>
            <div class="navbar-dropdown-content">
                <a href="your_profile.php">My Profile</a>
                <a href="your_orders.php">Orders</a>
                <a href="../logout.php">Logout</a>
            </div>
        </div>
    </div>
</header>

<section class="dashboard-scoped">
    <div class="dashboard-hero">
        <div class="dashboard-left">
            <h1 class="dashboard-title">WHERE FLAVOR<br>LINGERS</h1>
            <p class="dashboard-text">
                Welcome back, <?php echo htmlspecialchars($username); ?>! 
                Where comfort, conversation, and good food come together.
            </p>
            <div class="dashboard-buttons">
                <a href="aboutus.php" class="dashboard-btn">About Us</a>
            </div>
        </div>
    </div>
</section>

<script>
const allProducts = <?php echo json_encode($productNames); ?>;
const input = document.querySelector('.navbar-search');
const box = document.getElementById('suggestion-box');

input.addEventListener('input', function() {
    // 1. Reset logic: Redirect to products if empty
    if (this.value.trim() === '') {
        // No redirect needed if already on index, but good for consistency
        box.style.display = 'none';
        return;
    }

    // 2. Suggestion logic
    const val = this.value.toLowerCase();
    box.innerHTML = '';
    
    if (val.length < 2) { 
        box.style.display = 'none'; 
        return; 
    }
    
    const matches = allProducts.filter(p => p.toLowerCase().includes(val));
    
    matches.forEach(m => {
        const item = document.createElement('div');
        item.innerText = m; // Plain text
        item.onclick = () => { 
            input.value = m; 
            input.form.submit(); 
        };
        box.appendChild(item);
    });
    
    box.style.display = matches.length ? 'block' : 'none';
});

// Close when clicking elsewhere
document.addEventListener('click', (e) => {
    if (!input.contains(e.target) && !box.contains(e.target)) {
        box.style.display = 'none';
    }
});
</script>

</body>
</html>