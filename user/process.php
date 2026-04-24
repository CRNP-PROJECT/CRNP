<?php
session_start();

include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

$rdb = new firebaseRDB($databaseURL);

date_default_timezone_set("Asia/Manila");

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch($action) {

    // ================= ADD TO CART =================
    case 'add_to_cart':
        $product_id = $_POST['product_id'] ?? '';

        if ($product_id == "") {
            header("Location: products.php");
            exit;
        }

        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        $_SESSION['cart'][$product_id] = ($_SESSION['cart'][$product_id] ?? 0) + 1;

        header("Location: products.php");
        exit;


    // ================= BUY NOW (FIXED) =================
    case 'buy_now':
        $product_id = $_POST['product_id'] ?? '';

        if ($product_id == "") {
            header("Location: products.php");
            exit;
        }

        // 🔥 FIX: overwrite cart with single item
        $_SESSION['cart'] = [
            $product_id => 1
        ];

        header("Location: checkout.php");
        exit;


    // ================= UPDATE CART =================
    case 'update_cart':
        $product_id = $_POST['product_id'] ?? '';
        $qty = intval($_POST['quantity'] ?? 1);

        if ($product_id && $qty > 0) {
            $_SESSION['cart'][$product_id] = $qty;
        }

        header("Location: cart.php");
        exit;


    // ================= REMOVE CART =================
    case 'remove_cart':
        $id = $_GET['id'] ?? '';

        if ($id && isset($_SESSION['cart'][$id])) {
            unset($_SESSION['cart'][$id]);
        }

        header("Location: cart.php");
        exit;


    // ================= CONFIRM CHECKOUT =================
    case 'confirm_checkout':

        if (!isset($_SESSION['email'])) {
            header("Location: user_login.php");
            exit;
        }

        if (empty($_SESSION['cart'])) {
            header("Location: cart.php");
            exit;
        }

        $full_name = $_POST['full_name'] ?? '';
        $contact_number = $_POST['contact_number'] ?? '';
        $num_people = intval($_POST['num_people'] ?? 1);

        $raw_time = $_POST['appointment_time'] ?? '';
        $timestamp = strtotime($raw_time);

        $appointment_time = $timestamp
            ? date("M d, Y h:i A", $timestamp)
            : $raw_time;

        $cart = $_SESSION['cart'];
        $total = 0;
        $products_detail = [];

        foreach ($cart as $id => $qty) {

            $product = json_decode($rdb->retrieve("/products/$id"), true);

            if ($product) {
                $subtotal = floatval($product['price']) * $qty;
                $total += $subtotal;

                $products_detail[$id] = [
                    'name' => $product['name'],
                    'price' => $product['price'],
                    'qty' => $qty,
                    'subtotal' => $subtotal
                ];
            }
        }

        $order_data = [
            'user_email' => $_SESSION['email'],
            'full_name' => $full_name,
            'contact_number' => $contact_number,
            'num_people' => $num_people,
            'appointment_time' => $appointment_time,
            'products' => $products_detail,
            'total' => $total,
            'status' => 'pending',
            'created_at' => date("M d, Y h:i A")
        ];

        $rdb->insert("/orders", $order_data);

        unset($_SESSION['cart']);

        header("Location: checkout_success.php");
        exit;


    // ================= BOOKING =================
    case 'booking':

        if (!isset($_SESSION['email'])) {
            header("Location: user_login.php");
            exit;
        }

        $full_name = $_POST['full_name'] ?? '';
        $contact_number = $_POST['contact_number'] ?? '';
        $address = $_POST['address'] ?? '';
        $appointment_time = $_POST['appointment_time'] ?? '';
        $tables_qty = intval($_POST['tables_qty'] ?? 0);
        $chairs_qty = intval($_POST['chairs_qty'] ?? 0);

        $skirting = [];

        if (isset($_POST['skirting_color']) && is_array($_POST['skirting_color'])) {
            foreach ($_POST['skirting_color'] as $i => $color) {
                $qty = intval($_POST['skirting_qty'][$i] ?? 0);

                if ($qty > 0) {
                    $skirting[] = [
                        'color' => $color,
                        'qty' => $qty
                    ];
                }
            }
        }

        $booking_data = [
            'user_email' => $_SESSION['email'],
            'full_name' => $full_name,
            'contact_number' => $contact_number,
            'address' => $address,
            'appointment_time' => $appointment_time,
            'tables_qty' => $tables_qty,
            'chairs_qty' => $chairs_qty,
            'skirting' => $skirting,
            'created_at' => date("M d, Y h:i A")
        ];

        $rdb->insert("/bookings", $booking_data);

        header("Location: booking_success.php");
        exit;


    // ================= UPDATE PROFILE =================
    case 'update_profile':

        if (!isset($_SESSION['user_id'])) {
            header("Location: user_login.php");
            exit;
        }

        $user_id = $_SESSION['user_id'];

        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        if ($name == "" || $email == "") {
            header("Location: your_profile.php?status=error");
            exit;
        }

        $updateData = [
            "name" => $name,
            "email" => $email
        ];

        if (!empty($password)) {
            $updateData["password"] = password_hash($password, PASSWORD_DEFAULT);
        }

        $result = $rdb->update("/user", $user_id, $updateData);

        if ($result) {
            $_SESSION['email'] = $email;
            $_SESSION['user_name'] = $name;

            header("Location: your_profile.php?status=success");
            exit;
        }

        header("Location: your_profile.php?status=error");
        exit;
}
?>