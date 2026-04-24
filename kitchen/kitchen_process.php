<?php
session_start();

include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

$rdb = new firebaseRDB($databaseURL);

$action = $_POST['action'] ?? '';

switch($action){

// ================= SIGNUP =================
case 'signup':
    $full_name = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if(!$email || !$password){
        die("Email and password required");
    }

    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    $rdb->insert("/kitchen", [
        'full_name' => $full_name,
        'email' => $email,
        'password' => $password_hash,
        'created_at' => date('Y-m-d H:i:s')
    ]);

    header("Location: kitchen_login.php");
    exit;


// ================= LOGIN =================
case 'login':
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $all_kitchen = json_decode($rdb->retrieve("/kitchen"), true) ?? [];

    foreach($all_kitchen as $k){
        if(($k['email'] ?? '') === $email && password_verify($password, $k['password'] ?? '')){
            $_SESSION['kitchen_email'] = $email;
            header("Location: kitchen_index.php");
            exit;
        }
    }

    die("Invalid credentials");


// ================= UPDATE ORDER STATUS =================
case 'update_status':

    if(!isset($_SESSION['kitchen_email'])){
        header("Location: kitchen_login.php");
        exit;
    }

    $order_id = $_POST['order_id'] ?? '';
    $status = $_POST['status'] ?? '';

    if(!$order_id || !$status){
        die("Missing data");
    }

    $validStatuses = ['accepted', 'preparing', 'ready', 'done'];

    if(!in_array($status, $validStatuses)){
        die("Invalid kitchen status");
    }

    $order = json_decode($rdb->retrieve("/orders/$order_id"), true);

    if(!$order){
        die("Order not found");
    }

    // ================= PREPARING / READY =================
    if($status !== "done"){

        // ONLY update kitchen progress
        $rdb->update("/orders", $order_id, [
            "kitchen_status" => $status,
            "kitchen_action_time" => date("Y-m-d H:i:s")
        ]);
    }

    // ================= DONE =================
    else {

        // IMPORTANT: DO NOT change cashier status anymore
        $rdb->update("/orders", $order_id, [
            "kitchen_status" => "done",
            "kitchen_action_time" => date("Y-m-d H:i:s"),
            "processed_by_kitchen" => $_SESSION['kitchen_email']
        ]);

        // LOG ONLY (DO NOT AFFECT ORDER HISTORY LOGIC)
        $rdb->insert("/kitchen_history", [
            "order_id" => $order_id,
            "full_name" => $order['full_name'] ?? 'N/A',
            "table_number" => $order['table_number'] ?? '',
            "payment_method" => $order['payment_method'] ?? '',
            "products" => $order['products'] ?? [],
            "total" => $order['total'] ?? 0,
            "order_type" => !empty($order['cashier']) ? "WALK-IN" : "ONLINE",
            "kitchen_status" => "done",
            "kitchen_action_time" => date("Y-m-d H:i:s"),
            "processed_by" => $_SESSION['kitchen_email']
        ]);
    }

    header("Location: kitchen_index.php");
    exit;

}
?>