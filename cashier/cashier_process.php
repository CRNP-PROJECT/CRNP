<?php
session_start();

include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

$rdb = new firebaseRDB($databaseURL);

$action = $_POST['action'] ?? '';

switch($action){


// ================= LOGIN =================
case 'login':

    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $cashiers = json_decode($rdb->retrieve("/cashiers"), true) ?? [];

    foreach($cashiers as $c){

    if(strtolower($c['email'] ?? '') === strtolower($email)
       && password_verify($password, $c['password'] ?? '')){

        $_SESSION['cashier_email'] = $email;
        $_SESSION['cashier_name'] = $c['full_name']; // ✅ ADD THIS

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


// ================= ORDER STATUS =================
case 'update_status':

    $order_id = $_POST['order_id'] ?? '';
    $status = strtolower(trim($_POST['status'] ?? ''));

    if(!$order_id || !$status){
        header("Location: view_orders.php");
        exit;
    }

    $order = json_decode($rdb->retrieve("/orders/$order_id"), true);

    if(!$order){
        header("Location: view_orders.php");
        exit;
    }

    $order['status'] = $status;
    $order['final_status'] = $status;
    $order['cashier_action_time'] = date("Y-m-d H:i:s");

    if($status === "rejected"){
        $order['payment_status'] = null;
        $order['payment_verified'] = false;
        $order['paid_at'] = null;
    }

    $rdb->update("/orders", $order_id, $order);

    header("Location: view_orders.php");
    exit;


// ================= ORDER PAYMENT =================
case 'mark_paid':

    $order_id = $_POST['order_id'] ?? '';

    if(!$order_id){
        header("Location: payment_status.php");
        exit;
    }

    $order = json_decode($rdb->retrieve("/orders/$order_id"), true);

    if($order && ($order['status'] ?? '') !== "rejected"){

        $order['payment_status'] = "paid";
        $order['payment_verified'] = true;
        $order['paid_at'] = date("Y-m-d H:i:s");

        $rdb->update("/orders", $order_id, $order);
    }

    header("Location: payment_status.php");
    exit;


// ================= ORDER NOT PAID =================
case 'mark_not_paid':

    $order_id = $_POST['order_id'] ?? '';

    if(!$order_id){
        header("Location: payment_status.php");
        exit;
    }

    $order = json_decode($rdb->retrieve("/orders/$order_id"), true);

    if($order && ($order['status'] ?? '') !== "rejected"){

        $order['payment_status'] = "not_paid";
        $order['payment_verified'] = false;

        $rdb->update("/orders", $order_id, $order);
    }

    header("Location: payment_status.php");
    exit;


// ================= BOOKING STATUS =================
case 'update_booking_status':

    $booking_id = $_POST['booking_id'] ?? '';
    $status = strtolower(trim($_POST['status'] ?? ''));

    if(!$booking_id || !$status){
        header("Location: view_booking.php");
        exit;
    }

    $booking = json_decode($rdb->retrieve("/bookings/$booking_id"), true);

    if(!$booking){
        header("Location: view_booking.php");
        exit;
    }

    $booking['status'] = $status;
    $booking['updated_at'] = date("Y-m-d H:i:s");

    if($status === "rejected"){
        $booking['payment_status'] = null;
        $booking['payment_verified'] = false;
    }

    $rdb->update("/bookings", $booking_id, $booking);

    header("Location: view_bookings.php");
    exit;


// ================= NEW FIX: BOOKING PAYMENT =================
case 'mark_booking_paid':

    $booking_id = $_POST['booking_id'] ?? '';

    if(!$booking_id){
        header("Location: booking_payment.php");
        exit;
    }

    $booking = json_decode($rdb->retrieve("/bookings/$booking_id"), true);

    if($booking){

        $booking['payment_status'] = "paid";
        $booking['payment_verified'] = true;
        $booking['paid_at'] = date("Y-m-d H:i:s");

        $rdb->update("/bookings", $booking_id, $booking);
    }

    header("Location: booking_payment.php");
    exit;


// ================= DEFAULT =================
default:
    header("Location: cashier_login.php");
    exit;

}

?>