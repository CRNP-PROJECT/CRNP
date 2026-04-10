<?php
session_start();
include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

$rdb = new firebaseRDB($databaseURL);
$action = $_POST['action'] ?? '';

// ===== HANDLE CASHIER ACTIONS =====
switch($action){

    // ===== SIGNUP =====
    case 'signup':
        $full_name = $_POST['full_name'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        if(!$email || !$password){
            die("Email and password are required.");
        }

        // Check if email already exists
        $cashiers = json_decode($rdb->retrieve("/cashiers"), true) ?? [];
        foreach($cashiers as $id => $c){
            if(($c['email'] ?? '') === $email){
                die("Email already exists.");
            }
        }

        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $cashier_data = [
            'full_name' => $full_name,
            'email' => $email,
            'password' => $password_hash,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $rdb->insert("/cashiers", $cashier_data);
        $_SESSION['cashier_email'] = $email;
        header("Location: cashier_index.php");
        exit;

    // ===== LOGIN =====
    case 'login':
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        $cashiers = json_decode($rdb->retrieve("/cashiers"), true) ?? [];

        if(is_array($cashiers)){
            foreach($cashiers as $id => $c){
                if(($c['email'] ?? '') === $email && password_verify($password, $c['password'] ?? '')){
                    $_SESSION['cashier_email'] = $email;
                    header("Location: cashier_index.php");
                    exit;
                }
            }
        }

        die("Invalid email or password");

    // ===== LOGOUT =====
    case 'logout':
        session_destroy();
        header("Location: cashier_login.php");
        exit;

    // ===== UPDATE ORDER STATUS (ACCEPT / REJECT) =====
     case 'update_status':

        if(!isset($_SESSION['cashier_email'])){
            header("Location: cashier_login.php");
            exit;
        }

        $order_id = $_POST['order_id'] ?? '';
        $status = $_POST['status'] ?? '';

        if($order_id == '' || $status == ''){
            die("Missing data");
        }

        // Only allow valid values
        if(!in_array($status, ['accepted', 'rejected'])){
            die("Invalid status");
        }

        // ✅ THIS IS THE MOST IMPORTANT FIX
       $rdb->update("orders", $order_id, [
    'status' => $status
]);

        header("Location: view_orders.php");
        exit;

    default:
        header("Location: cashier_login.php");
        exit;
            // ===== UPDATE BOOKING STATUS (ACCEPT / REJECT) =====
    case 'update_booking_status':

        if(!isset($_SESSION['cashier_email'])){
            header("Location: cashier_login.php");
            exit;
        }

        $booking_id = $_POST['booking_id'] ?? '';
        $status = $_POST['status'] ?? '';

        if($booking_id == '' || $status == ''){
            die("Missing data");
        }

        // Allow only valid values
        if(!in_array($status, ['accepted', 'rejected'])){
            die("Invalid status");
        }

        // Update booking status in Firebase
        $rdb->update("bookings", $booking_id, [
            'status' => $status
        ]);

        header("Location: view_bookings.php");
        exit;
        
        case 'create_order':

    if(!isset($_SESSION['cashier_email'])){
        header("Location: cashier_login.php");
        exit;
    }

    $cart_data = $_POST['cart_data'] ?? '';
    $customer_name = $_POST['customer_name'] ?? '';
    $table_number = $_POST['table_number'] ?? '';
    $payment_method = $_POST['payment_method'] ?? '';

    if(!$cart_data || !$customer_name || !$table_number){
        die("Missing order data");
    }

    $cart = json_decode($cart_data, true);

    if(empty($cart)){
        die("Cart is empty");
    }

    $total = 0;

    foreach($cart as $item){
        $total += $item['price'] * $item['qty'];
    }

    $order = [
        "customer_name" => $customer_name,
        "table_number" => $table_number,
        "payment_method" => $payment_method,
        "items" => $cart,
        "total" => $total,
        "status" => "pending",
        "created_at" => date("Y-m-d H:i:s"),
        "cashier" => $_SESSION['cashier_email']
    ];

    $rdb->insert("/orders", $order);

    header("Location: cashier_index.php?success=1");
    exit;
}

?>