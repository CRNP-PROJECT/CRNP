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

// Get user data from Firebase
$data = $rdb->retrieve("/user/$user_id");
$user = json_decode($data, true);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Your Profile</title>
</head>
<body>

<h2>Edit Profile</h2>

<!-- ✅ SHOW SUCCESS / ERROR -->
<?php
if(isset($_GET['status'])){
    if($_GET['status'] == "success"){
        echo "<p style='color:green;'>Profile updated successfully!</p>";
    } elseif($_GET['status'] == "error"){
        echo "<p style='color:red;'>Update failed. Please try again.</p>";
    }
}
?>

<form action="process.php" method="POST">

    <!-- ✅ IMPORTANT: ADD THIS -->
    <input type="hidden" name="action" value="update_profile">

    <!-- ✅ SAFE OUTPUT -->
    <input type="text" name="name"
        value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required><br><br>

    <input type="email" name="email"
        value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required><br><br>

    <input type="password" name="password"
        placeholder="New Password (leave blank if no change)"><br><br>

    <!-- ❌ REMOVE name="update_profile" -->
    <button type="submit">Update Profile</button>

</form>

</body>
</html><?php
session_start();

if(!isset($_SESSION['user_id'])){
    header("Location: user_login.php");
    exit;
}

include("../config.php");
include("../firebaseRDB.php");

$rdb = new firebaseRDB($databaseURL);
$user_id = $_SESSION['user_id'];

// Get user data from Firebase
$data = $rdb->retrieve("/user/$user_id");
$user = json_decode($data, true);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Your Profile</title>
</head>
<body>

<h2>Edit Profile</h2>

<!-- ✅ SHOW SUCCESS / ERROR -->
<?php
if(isset($_GET['status'])){
    if($_GET['status'] == "success"){
        echo "<p style='color:green;'>Profile updated successfully!</p>";
    } elseif($_GET['status'] == "error"){
        echo "<p style='color:red;'>Update failed. Please try again.</p>";
    }
}
?>

<form action="process.php" method="POST">

    <!-- ✅ IMPORTANT: ADD THIS -->
    <input type="hidden" name="action" value="update_profile">

    <!-- ✅ SAFE OUTPUT -->
    <input type="text" name="name"
        value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required><br><br>

    <input type="email" name="email"
        value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required><br><br>

    <input type="password" name="password"
        placeholder="New Password (leave blank if no change)"><br><br>

    <!-- ❌ REMOVE name="update_profile" -->
    <button type="submit">Update Profile</button>

</form>

</body>
</html><?php
session_start();

if(!isset($_SESSION['user_id'])){
    header("Location: user_login.php");
    exit;
}

include("../config.php");
include("../firebaseRDB.php");

$rdb = new firebaseRDB($databaseURL);
$user_id = $_SESSION['user_id'];

// Get user data from Firebase
$data = $rdb->retrieve("/user/$user_id");
$user = json_decode($data, true);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Your Profile</title>
</head>
<body>

<h2>Edit Profile</h2>

<!-- ✅ SHOW SUCCESS / ERROR -->
<?php
if(isset($_GET['status'])){
    if($_GET['status'] == "success"){
        echo "<p style='color:green;'>Profile updated successfully!</p>";
    } elseif($_GET['status'] == "error"){
        echo "<p style='color:red;'>Update failed. Please try again.</p>";
    }
}
?>

<form action="process.php" method="POST">

    <!-- ✅ IMPORTANT: ADD THIS -->
    <input type="hidden" name="action" value="update_profile">

    <!-- ✅ SAFE OUTPUT -->
    <input type="text" name="name"
        value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required><br><br>

    <input type="email" name="email"
        value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required><br><br>

    <input type="password" name="password"
        placeholder="New Password (leave blank if no change)"><br><br>

    <!-- ❌ REMOVE name="update_profile" -->
    <button type="submit">Update Profile</button>

</form>

</body>
</html>