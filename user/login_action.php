<?php

include("../config.php");
include("../firebaseRDB.php");

$email = $_POST['email'];
$password = $_POST['password'];

// Validation
if($email == ""){
    echo "Email is required";
    exit;
}

if($password == ""){
    echo "Password is required";
    exit;
}

try {
    $rdb = new firebaseRDB($databaseURL);

    // Retrieve user by email
    $retrieve = $rdb->retrieve("/user", "email", "EQUAL", $email);
    $data = json_decode($retrieve, true);

    if(!is_array($data) || count($data) == 0){
        echo "Email not registered";
        exit;
    }

    $id = array_keys($data)[0];

    // Make sure password field exists
    if(!isset($data[$id]['password'])){
        echo "User password not set";
        exit;
    }

    // Verify password
    if(password_verify($password, $data[$id]['password'])){
        // Login success: set session
        session_regenerate_id(true);
        $_SESSION['user_id'] = $id;
        $_SESSION['email'] = $email;
        $_SESSION['username'] = $data[$id]['name'] ?? '';

        // Pure PHP redirect to index.php
        header("Location: index.php"); // Adjust folder path
        exit; // Always exit after redirect

    } else {
        echo "Login failed";
    }

} catch(Exception $e){
    echo "Error: " . $e->getMessage();
}
?>