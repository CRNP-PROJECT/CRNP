<?php
include("../config.php");
include("../firebaseRDB.php");

// Get POST data
$name = $_POST['name'];
$email = $_POST['email'];
$password = $_POST['password'];

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

// Hash password for security
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

try {
    $rdb = new firebaseRDB($databaseURL);

    // Check if email already exists
    $retrieve = $rdb->retrieve("/user", "email", "EQUAL", $email);
    $data = json_decode($retrieve, true);

    if(is_array($data) && count($data) > 0){
        echo "Email already registered";
        exit;
    }

    // Insert user into Firebase
    $insert = $rdb->insert("/user", [
        "name" => $name,
        "email" => $email,
        "password" => $hashedPassword
    ]);

    $result = json_decode($insert, true);

    if($result != null){
        // ✅ Show confirmation page with automatic redirect
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Signup Successful</title>
            <!-- Meta refresh will redirect after 3 seconds -->
            <meta http-equiv="refresh" content="3;url=login.php">
            <style>
                body { font-family: Arial, sans-serif; text-align: center; margin-top: 100px; }
                .message { font-size: 20px; color: green; }
            </style>
        </head>
        <body>
            <p class="message">Sign up successful! Redirecting to login page...</p>
            <p>If you are not redirected, <a href="login.php">click here</a>.</p>
        </body>
        </html>
        <?php
        exit;
    } else {
        echo "Sign up failed";
    }

} catch(Exception $e){
    echo "Error: " . $e->getMessage();
}
?>