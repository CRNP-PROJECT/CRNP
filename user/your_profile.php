<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: user_login.php");
    exit;
}

include("../config.php");
include("../firebaseRDB.php");

$rdb = new firebaseRDB($databaseURL);
$user_id = $_SESSION['user_id'];

$data = $rdb->retrieve("/user/$user_id");
$user = json_decode($data, true) ?? [];

$username = $user['name'] ?? $_SESSION['username'] ?? "User";
$email = $user['email'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Your Profile</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../styles.css">
</head>

<body class="your_profile">

<!-- ✅ NAVBAR -->
<header class="navbar">
    <div class="navbar-brand-container">
        <img src="../img/logo.png" class="logo">
    </div>

    <div class="navbar-right">
        <ul class="navbar-menu">
            <li><a href="index.php">Home</a></li>
            <li><a href="products.php">Products</a></li>
            <li><a href="booking.php">Booking</a></li>
            <li><a href="cart.php">Cart</a></li>
            <li><a href="aboutus.php">About</a></li>
        </ul>

        <form action="products.php" method="GET" class="search-box" style="position: relative;">
            <button type="submit" style="background:none; border:none; cursor:pointer; color:inherit;">
                <i class="fa-solid fa-magnifying-glass"></i>
            </button>
            <input type="text" name="search" placeholder="Search..." class="navbar-search">
            <div id="suggestion-box"></div>
        </form>

        <div class="navbar-dropdown">
            <span class="navbar-user-btn">
                <i class="fa-regular fa-user"></i>
                <?= htmlspecialchars($username) ?>
            </span>

            <div class="navbar-dropdown-content">
                <a href="your_profile.php">My Profile</a>
                <a href="your_orders.php">Orders</a>
                <a href="../logout.php">Logout</a>
            </div>
        </div>
    </div>
</header>

<!-- ✅ PROFILE -->
<div class="your_profile-wrapper">

    <div class="your_profile-card">

        <form action="process.php" method="POST" enctype="multipart/form-data">

            <input type="hidden" name="action" value="update_profile">

            <!-- HEADER -->
            <div class="your_profile-header">

                <div class="your_profile-avatar">

                    <!-- PROFILE IMAGE -->
                    <?php if (!empty($user['profile_image'])): ?>
                        <img id="previewImage"
                             src="<?= htmlspecialchars($user['profile_image']) ?>"
                             alt="Profile">
                    <?php else: ?>
                        <div class="your_profile-avatar-icon" id="avatarIcon">
                            <?= strtoupper(substr($username, 0, 1)) ?>
                        </div>
                        <img id="previewImage" style="display:none;">
                    <?php endif; ?>

                    <!-- UPLOAD BUTTON -->
                    <label class="your_profile-upload">
                        <i class="fa-solid fa-camera"></i>
                        <input type="file" name="profile_image" id="imageInput" hidden>
                    </label>

                </div>

                <h2><?= htmlspecialchars($username) ?></h2>
                <p class="your_profile-sub"><?= htmlspecialchars($email) ?></p>

            </div>

            <!-- DIVIDER -->
            <hr class="your_profile-divider">

            <!-- FORM -->
            <div class="your_profile-group">
                <label>Name</label>
                <input type="text" name="name"
                    value="<?= htmlspecialchars($user['name'] ?? '') ?>">
            </div>

            <div class="your_profile-group">
                <label>Email Address</label>
                <input type="email" name="email"
                    value="<?= htmlspecialchars($email) ?>">
            </div>

            <div class="your_profile-group">
                <label>Password</label>
                <input type="password" name="password"
                    placeholder="••••••••">
            </div>

            <button type="submit" class="your_profile-save"> Save Changes
            </button>

        </form>

    </div>

</div>

<!-- ✅ IMAGE PREVIEW SCRIPT -->
<script>
const input = document.getElementById("imageInput");
const preview = document.getElementById("previewImage");
const icon = document.getElementById("avatarIcon");

input.addEventListener("change", function(e) {
    const file = e.target.files[0];

    if (file) {
        preview.src = URL.createObjectURL(file);
        preview.style.display = "block";

        if (icon) icon.style.display = "none";
    }
});
</script>

</body>
</html>