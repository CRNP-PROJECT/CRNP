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
        die("Email and password are required.");
    }

    $cashiers = json_decode($rdb->retrieve("/cashiers"), true) ?? [];

    foreach($cashiers as $c){
        if(($c['email'] ?? '') === $email){
            die("Email already exists.");
        }
    }

    $rdb->insert("/cashiers", [
        'full_name' => $full_name,
        'email' => $email,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'created_at' => date('Y-m-d H:i:s')
    ]);

    $_SESSION['cashier_email'] = $email;
    header("Location: cashier_index.php");
    exit;


// ================= LOGIN =================
case 'login':

    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $cashiers = json_decode($rdb->retrieve("/cashiers"), true) ?? [];

    foreach($cashiers as $c){
        if(($c['email'] ?? '') === $email && password_verify($password, $c['password'] ?? '')){
            $_SESSION['cashier_email'] = $email;
            header("Location: cashier_index.php");
            exit;
        }
    }

    die("Invalid email or password");


// ================= LOGOUT =================
case 'logout':
    session_destroy();
    header("Location: cashier_login.php");
    exit;


// ================= UPDATE ORDER STATUS =================
case 'update_status':

    if(!isset($_SESSION['cashier_email'])){
        header("Location: cashier_login.php");
        exit;
    }

    $order_id = $_POST['order_id'] ?? '';
    $status = $_POST['status'] ?? '';

    if(!$order_id || !$status){
        header("Location: view_orders.php");
        exit;
    }

    if(!in_array($status, ['accepted', 'rejected', 'done'])){
        header("Location: view_orders.php");
        exit;
    }

    $order = json_decode($rdb->retrieve("/orders/$order_id"), true);

    if(!$order){
        header("Location: view_orders.php");
        exit;
    }

    // ================= FIXED LOGIC =================
    $order['order_type'] = $order['order_type'] ?? 'online';

    $order['status'] = $status;
    $order['final_status'] = $status;

    // ❌ DO NOT TOUCH kitchen_status anymore
    // $order['kitchen_status'] = $status;

    $order['cashier_action_time'] = date("Y-m-d H:i:s");
    $order['processed_by'] = $_SESSION['cashier_email'];

    $rdb->update("/orders", $order_id, $order);

    header("Location: view_orders.php");
    exit;


// ================= CREATE WALK-IN ORDER =================
case 'create_order':

    if(!isset($_SESSION['cashier_email'])){
        header("Location: cashier_login.php");
        exit;
    }

    $cart_data = $_POST['cart_data'] ?? '';
    $customer_name = $_POST['customer_name'] ?? '';
    $table_number = $_POST['table_number'] ?? '';

    if(!$cart_data || !$customer_name || !$table_number){
        die("Missing order data");
    }

    $cart = json_decode($cart_data, true);

    $products = [];
    $total = 0;

    foreach($cart as $id => $item){
        $subtotal = $item['price'] * $item['qty'];
        $total += $subtotal;

        $products[] = [
            "id" => $id,
            "name" => $item['name'],
            "price" => $item['price'],
            "qty" => $item['qty'],
            "subtotal" => $subtotal
        ];
    }

    $order_id = uniqid("walkin_");

    $order = [
        "order_id" => $order_id,
        "order_type" => "walkin",

        "full_name" => $customer_name,
        "table_number" => $table_number,
        "products" => $products,
        "total" => $total,

        // ✔ FIXED: kitchen should start as pending
        "status" => "accepted",
        "final_status" => "accepted",
        "kitchen_status" => "pending",

        "cashier" => $_SESSION['cashier_email'],
        "processed_by" => $_SESSION['cashier_email'],

        "created_at" => date("Y-m-d H:i:s"),
        "cashier_action_time" => date("Y-m-d H:i:s")
    ];

    $rdb->insert("/orders", $order);

    header("Location: receipt.php");
    exit;


// ================= BOOKING =================
case 'update_booking_status':

    if(!isset($_SESSION['cashier_email'])){
        header("Location: cashier_login.php");
        exit;
    }

    $booking_id = $_POST['booking_id'] ?? '';
    $status = $_POST['status'] ?? '';

    if(!$booking_id || !$status){
        header("Location: view_bookings.php");
        exit;
    }

    if(!in_array($status, ['accepted', 'rejected'])){
        header("Location: view_bookings.php");
        exit;
    }

    $booking = json_decode($rdb->retrieve("/bookings/$booking_id"), true);

    if(!$booking){
        header("Location: view_bookings.php");
        exit;
    }

    $booking['booking_id'] = $booking_id;
    $booking['status'] = $status;
    $booking['final_status'] = $status;
    $booking['processed_by'] = $_SESSION['cashier_email'];
    $booking['cashier_action_time'] = date("Y-m-d H:i:s");

    if($status === "accepted"){
        $rdb->update("/bookings", $booking_id, $booking);
        $rdb->insert("/cashier_bookinghistory", $booking);
    }

    if($status === "rejected"){
        $rdb->delete("/bookings", $booking_id);
        $rdb->insert("/cashier_bookinghistory", $booking);
    }

    header("Location: view_bookings.php");
    exit;


default:
    header("Location: cashier_login.php");
    exit;
}
?>