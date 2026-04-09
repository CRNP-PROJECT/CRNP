<?php
include("../config.php");
include("../firebaseRDB.php");

// Get POST data
$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
$admin_key = $_POST['admin_key'] ?? '';

// 🔐 Set your admin secret key
$valid_key = "ADMIN123";

// Basic validation
if($name == ""){
    echo "Name is required";
    exit;
}

if($email == ""){
    echo "Email is required";
    exit;
}

if($password == ""){
    echo "Password is required";
    exit;
}

if($admin_key == ""){
    echo "Admin key is required";
    exit;
}

// Check admin key
if($admin_key !== $valid_key){
    echo "Invalid admin key";
    exit;
}

// Hash password for security
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

try {
    $rdb = new firebaseRDB($databaseURL);

    // Check if admin email already exists
    $retrieve = $rdb->retrieve("/admin", "email", "EQUAL", $email);
    $data = json_decode($retrieve, true);

    if(is_array($data) && count($data) > 0){
        echo "Admin email already exists";
        exit;
    }

    // Insert admin into Firebase
    $insert = $rdb->insert("/admin", [
        "name" => $name,
        "email" => $email,
        "password" => $hashedPassword
    ]);

    $result = json_decode($insert, true);

    if($result != null){
        // Redirect to admin login
        header("Location: admin_login.php?success=1");
        exit;
    } else {
        echo "Admin signup failed";
    }

} catch(Exception $e){
    echo "Error: " . $e->getMessage();
}
?>