<?php
session_start();

include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

$rdb = new firebaseRDB($databaseURL);

$action = $_POST['action'] ?? '';

switch($action){

// ================= LOGIN =================
case 'login':

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if($email == '' || $password == ''){
        die("All fields are required.");
    }

    $all_kitchen = json_decode($rdb->retrieve("/kitchen"), true) ?? [];

    foreach($all_kitchen as $k){

        if(
            strtolower($k['email'] ?? '') === strtolower($email) &&
            password_verify($password, $k['password'] ?? '')
        ){

            $_SESSION['kitchen_email'] = $email;
            $_SESSION['kitchen_name'] = $k['full_name'] ?? '';
            $_SESSION['role'] = "kitchen";

            header("Location: kitchen_index.php");
            exit;
        }
    }

    // If credentials don't match, redirect back with invalid error code
    header("Location: kitchen_login.php?error=invalid");
    exit;
    break;


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

    // ================= GET ORDER =================
    $order = json_decode($rdb->retrieve("/orders/$order_id"), true);

    if(!$order){
        die("Order not found");
    }

    $now = date("Y-m-d H:i:s");

    // ================= UPDATE ORDERS =================
    $updateOrder = [
        "kitchen_status" => $status,
        "kitchen_action_time" => $now
    ];

    $rdb->update("/orders", $order_id, $updateOrder);

    // ================= SYNC HISTORY =================
    $historyData = [
        "order_id" => $order_id,
        "full_name" => $order['full_name'] ?? 'N/A',
        "table_number" => $order['table_number'] ?? '',
        "payment_method" => $order['payment_method'] ?? '',
        "products" => $order['products'] ?? [],
        "total" => $order['total'] ?? 0,
        "order_type" => !empty($order['cashier']) ? "WALK-IN" : "ONLINE",
        "kitchen_status" => $status,
        "kitchen_action_time" => $now,
        "processed_by" => $_SESSION['kitchen_email']
    ];

    $rdb->update("/kitchen_history", $order_id, $historyData);

    header("Location: kitchen_index.php");
    exit;

    break;


// ================= DEFAULT =================
default:
    die("Invalid action");
}
?>