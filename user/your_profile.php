<?php
session_start();

if(!isset($_SESSION['user_id'])){
    header("Location: user_login.php");
    exit;
}

include("../config.php");
include("../firebaseRDB.php");

$rdb = new firebaseRDB($databaseURL);
$user_id = $_SESSION['user_id'];

$data = $rdb->retrieve("/user/$user_id");
$user = json_decode($data, true);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<!-- ICONS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<!-- YOUR CSS -->
<link rel="stylesheet" href="../styles.css">

<title>Your Profile</title>
</head>

<body class="your_profile">

<!-- NAVBAR -->
<nav class="navbar">
    <div class="navbar-brand-container">
        <img src="../img/logo.png" alt="Logo" class="logo">
        <a href="index.php" class="navbar-brand"></a>
    </div>

    <ul class="navbar-menu">
        <li><a href="index.php"><i class="fa-solid fa-house"></i> Home</a></li>
        <li><a href="products.php"><i class="fa-solid fa-shop"></i> Products</a></li>
        <li><a href="booking.php"><i class="fa-solid fa-calendar-check"></i> Booking</a></li>
        <li><a href="profile.php" class="active"><i class="fa-solid fa-user"></i> Profile</a></li>
        <li><a href="../logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
    </ul>
</nav>

<!-- PROFILE CONTAINER -->
<div class="profile-container">
    <div class="profile-card">

        <h2><i class="fa-solid fa-user"></i> Edit Profile</h2>

        <!-- STATUS MESSAGE -->
        <?php
        if(isset($_GET['status'])){
            if($_GET['status'] == "success"){
                echo "<p class='profile-message success'><i class='fa-solid fa-circle-check'></i> Profile updated successfully!</p>";
            } elseif($_GET['status'] == "error"){
                echo "<p class='profile-message error'><i class='fa-solid fa-circle-xmark'></i> Update failed. Please try again.</p>";
            }
        }
        ?>

        <!-- FORM -->
        <form action="process.php" method="POST">

            <input type="hidden" name="action" value="update_profile">

            <!-- NAME -->
            <div class="input-group">
                <i class="fa-solid fa-user"></i>
                <input type="text" name="name"
                    value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>
            </div>

            <!-- EMAIL -->
            <div class="input-group">
                <i class="fa-solid fa-envelope"></i>
                <input type="email" name="email"
                    value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
            </div>

            <!-- PASSWORD -->
            <div class="input-group">
                <i class="fa-solid fa-lock"></i>
                <input type="password" name="password"
                    placeholder="New Password (leave blank if no change)">
            </div>

            <!-- BUTTON -->
            <button type="submit">
                <i class=" "> </i> Update Profile
            </button>

        </form>

    </div>
</div>

</body>
</html>