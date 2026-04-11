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

        $cashier_data = [
            'full_name' => $full_name,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'created_at' => date('Y-m-d H:i:s')
        ];

        $rdb->insert("/cashiers", $cashier_data);

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


    // ================= CREATE ORDER (POS) =================
    case 'create_order':

        if(!isset($_SESSION['cashier_email'])){
            header("Location: cashier_login.php");
            exit;
        }

        $cart_data = $_POST['cart_data'] ?? '';
        $customer_name = $_POST['customer_name'] ?? '';
        $table_number = $_POST['table_number'] ?? '';
        $payment_method = $_POST['payment_method'] ?? 'Over the Counter';

        if(!$cart_data || !$customer_name || !$table_number){
            die("Missing order data");
        }

        $cart = json_decode($cart_data, true);

        if(empty($cart)){
            die("Cart is empty");
        }

        $items = [];
        $total = 0;

        foreach($cart as $item){
            $subtotal = $item['price'] * $item['qty'];
            $total += $subtotal;

            $items[] = [
                "name" => $item['name'],
                "price" => $item['price'],
                "qty" => $item['qty'],
                "subtotal" => $subtotal
            ];
        }

        $order = [
            "customer_name" => $customer_name,
            "table_number" => $table_number,
            "payment_method" => $payment_method,
            "items" => $items,
            "total" => $total,

            // 🔥 CASHIER AUTO ACCEPTED
            "status" => "accepted",
            "kitchen_status" => "pending",

            "created_at" => date("Y-m-d H:i:s"),
            "cashier" => $_SESSION['cashier_email']
        ];

        $rdb->insert("/orders", $order);

        header("Location: save_order.php?success=1");
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
            die("Missing data");
        }

        if(!in_array($status, ['accepted', 'rejected'])){
            die("Invalid status");
        }

        $rdb->update("orders", $order_id, [
            'status' => $status
        ]);

        header("Location: view_orders.php");
        exit;


    // ================= UPDATE BOOKING =================
    case 'update_booking_status':

        if(!isset($_SESSION['cashier_email'])){
            header("Location: cashier_login.php");
            exit;
        }

        $booking_id = $_POST['booking_id'] ?? '';
        $status = $_POST['status'] ?? '';

        if(!$booking_id || !$status){
            die("Missing data");
        }

        if(!in_array($status, ['accepted', 'rejected'])){
            die("Invalid status");
        }

        $rdb->update("bookings", $booking_id, [
            'status' => $status
        ]);

        header("Location: view_bookings.php");
        exit;


    // ================= DEFAULT =================
    default:
        header("Location: cashier_login.php");
        exit;

        case 'create_order':

    if(!isset($_SESSION['cashier_email'])){
        header("Location: cashier_login.php");
        exit;
    }

    $cart_data = $_POST['cart_data'] ?? '';
    $customer_name = $_POST['customer_name'] ?? '';
    $table_number = $_POST['table_number'] ?? '';
    $payment_method = $_POST['payment_method'] ?? 'Over the Counter';

    if(!$cart_data || !$customer_name || !$table_number){
        die("Missing order data");
    }

    $cart = json_decode($cart_data, true);

    if(empty($cart)){
        die("Cart is empty");
    }

    // compute total
    $total = 0;
    $products = [];

    foreach($cart as $id => $item){

        $subtotal = $item['price'] * $item['qty'];
        $total += $subtotal;

        // 🔥 FIX STRUCTURE FOR KITCHEN
        $products[] = [
            "id" => $id,
            "name" => $item['name'],
            "price" => $item['price'],
            "qty" => $item['qty'],
            "subtotal" => $subtotal
        ];
    }

    // 🔥 THIS IS NOW COMPATIBLE WITH KITCHEN
    $order = [
        "full_name" => $customer_name,   // FIXED (kitchen expects this)
        "table_number" => $table_number,
        "payment_method" => $payment_method,
        "products" => $products,         // FIXED (kitchen expects this)
        "total" => $total,

        "status" => "accepted",          // cashier auto-accept
        "kitchen_status" => "pending",

        "created_at" => date("Y-m-d H:i:s"),
        "cashier" => $_SESSION['cashier_email']
    ];

    $rdb->insert("/orders", $order);

    // 🔥 redirect to receipt page
    $_SESSION['last_order'] = $order;

    header("Location: receipt.php");
    exit;
}
?>