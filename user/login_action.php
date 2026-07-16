<?php

include("../config.php");
include("../firebaseRDB.php");

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

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

    // Retrieve all users, scan locally for email match
    $all = json_decode($rdb->retrieve("/user"), true);
    $id = null;
    $data = null;
    if (is_array($all)) {
        foreach ($all as $k => $u) {
            if (($u['email'] ?? '') === $email) { $id = $k; $data = $u; break; }
        }
    }

    if (!$id) {
        echo "Email not registered";
        exit;
    }

    // Support both password and password_hash field names
    $hashed = $data['password'] ?? $data['password_hash'] ?? '';

    if ($hashed === '') {
        echo "User password not set";
        exit;
    }

    // Verify password
    if (password_verify($password, $hashed)) {
        // Login success: set session
        session_regenerate_id(true);
        $_SESSION['user_id'] = $id;
        $_SESSION['email'] = $email;
        $_SESSION['username'] = $data['name'] ?? '';

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