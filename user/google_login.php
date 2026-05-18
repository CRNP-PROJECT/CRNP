

//https://console.cloud.google.com/auth/clients/1029428356414-ituvg05tojmq3sva030sab3cdog78fra.apps.googleusercontent.com?project=crnp-31b28
//$CLIENT_ID = "1029428356414-ituvg05tojmq3sva030sab3cdog78fra.apps.googleusercontent.com"


<?php
session_start();

include("../config.php");
include("../firebaseRDB.php");

// Get token from Google
$token = $_POST['token'] ?? '';

if($token == ""){
    echo "Google token missing";
    exit;
}

// Decode JWT token safely
$tokenParts = explode(".", $token);

if(count($tokenParts) != 3){
    echo "Invalid token";
    exit;
}

$payload = $tokenParts[1];

// FIX: base64url decode padding
$payload = str_replace(['-', '_'], ['+', '/'], $payload);
$payload .= str_repeat('=', 4 - strlen($payload) % 4);

$userData = json_decode(base64_decode($payload), true);

if(!$userData){
    echo "Unable to decode token";
    exit;
}

// Extract Google user info
$id = $userData['sub'] ?? '';
$name = $userData['name'] ?? '';
$email = $userData['email'] ?? '';

// Validation
if($id == ""){
    echo "Google ID is required";
    exit;
}

if($email == ""){
    echo "Email is required";
    exit;
}

try {

    $rdb = new firebaseRDB($databaseURL);

    // Check existing user
    $retrieve = $rdb->retrieve("/google_create_account", "id", "EQUAL", $id);
    $data = json_decode($retrieve, true);

    // User object
    $googleUser = [
        "id" => $id,
        "name" => $name,
        "email" => $email,
        "provider" => "google",
        "last_login" => date("c")
    ];

    // CREATE OR UPDATE USER
    if(!is_array($data) || count($data) == 0){

        $insert = $rdb->insert("/google_create_account", $googleUser);

        // DEBUG (optional)
        // file_put_contents("google_log.txt", print_r($insert, true));

    } else {

        $firebase_id = array_keys($data)[0];
        $rdb->update("/google_create_account", $firebase_id, $googleUser);
    }

    // SESSION LOGIN
    $_SESSION['id'] = $id;
    $_SESSION['name'] = $name;
    $_SESSION['email'] = $email;
    $_SESSION['provider'] = "google";

    // SUCCESS RESPONSE (important for JS)
    echo "success";

} catch(Exception $e){

    echo "Error: " . $e->getMessage();
}
?>