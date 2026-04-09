<?php
// ✅ Ensure session is started
include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

$rdb = new firebaseRDB($databaseURL);
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch($action) {

    // ===== ADD TO CART =====
    case 'add_to_cart':
        $product_id = $_POST['product_id'] ?? '';
        if ($product_id == "") {
            header("Location: products.php");
            exit;
        }

        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        // Increment quantity if already in cart
        if (isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id] += 1;
        } else {
            $_SESSION['cart'][$product_id] = 1;
        }

        header("Location: cart.php");
        exit;

    // ===== BUY NOW =====
    case 'buy_now':
        $product_id = $_POST['product_id'] ?? '';
        if ($product_id == "") {
            header("Location: products.php");
            exit;
        }

        $_SESSION['buy_now'] = [
            "product_id" => $product_id,
            "quantity" => 1
        ];

        header("Location: checkout.php");
        exit;

    // ===== UPDATE CART QUANTITY =====
    case 'update_cart':
        $product_id = $_POST['product_id'] ?? '';
        $qty = intval($_POST['quantity'] ?? 1);

        if ($product_id != '' && $qty > 0) {
            $_SESSION['cart'][$product_id] = $qty;
        }

        header("Location: cart.php");
        exit;

    // ===== REMOVE FROM CART =====
    case 'remove_cart':
        $id = $_GET['id'] ?? '';
        if ($id != '' && isset($_SESSION['cart'][$id])) {
            unset($_SESSION['cart'][$id]);
        }

        header("Location: cart.php");
        exit;

    // ===== CONFIRM CHECKOUT =====
    case 'confirm_checkout':
        if(!isset($_SESSION['email'])){
            header("Location: user_login.php");
            exit;
        }

        // Validate cart
        if(empty($_SESSION['cart'])){
            header("Location: cart.php");
            exit;
        }

        // Get form data
        $full_name = $_POST['full_name'] ?? '';
        $contact_number = $_POST['contact_number'] ?? '';
        $num_people = intval($_POST['num_people'] ?? 1);
        $appointment_time = $_POST['appointment_time'] ?? '';

        $cart = $_SESSION['cart'];
        $total = 0;
        $products_detail = [];

        foreach($cart as $id => $qty){
            $product = json_decode($rdb->retrieve("/products/$id"), true);
            if($product){
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
    'status' => 'pending',   // <-- new
    'created_at' => date('Y-m-d H:i:s')
];
$rdb->insert("/orders", $order_data);

        // Clear cart & buy_now
        unset($_SESSION['cart']);
        unset($_SESSION['buy_now']);

        // Redirect to success page
        header("Location: checkout_success.php");
        exit;

    // ===== BOOKING / RESERVATION =====
case 'booking':
    if(!isset($_SESSION['email'])) { 
        header("Location: user_login.php"); 
        exit; 
    }

    // Get form data
    $full_name = $_POST['full_name'] ?? '';
    $contact_number = $_POST['contact_number'] ?? '';
    $address = $_POST['address'] ?? '';
    $appointment_time = $_POST['appointment_time'] ?? '';
    $tables_qty = intval($_POST['tables_qty'] ?? 0);
    $chairs_qty = intval($_POST['chairs_qty'] ?? 0);

    // Handle multiple skirting colors and quantities
    $skirting = [];
    if(isset($_POST['skirting_color']) && is_array($_POST['skirting_color'])){
        foreach($_POST['skirting_color'] as $index => $color){
            $qty = intval($_POST['skirting_qty'][$index] ?? 0);
            if($qty > 0){
                $skirting[] = [
                    'color' => $color,
                    'qty' => $qty
                ];
            }
        }
    }

    // Save booking to Firebase
    $booking_data = [
        'user_email' => $_SESSION['email'],
        'full_name' => $full_name,
        'contact_number' => $contact_number,
        'address' => $address,
        'appointment_time' => $appointment_time,
        'tables_qty' => $tables_qty,
        'chairs_qty' => $chairs_qty,
        'skirting' => $skirting,  // store all colors with qty
        'created_at' => date('Y-m-d H:i:s')
    ];

    $rdb->insert("/bookings", $booking_data);

    header("Location: booking_success.php");
    exit;
    
        // ===== UPDATE PROFILE =====
    case 'update_profile':

        if(!isset($_SESSION['user_id'])){
            header("Location: user_login.php");
            exit;
        }

        $user_id = $_SESSION['user_id'];

        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        // Validate
        if($name == "" || $email == ""){
            header("Location: your_profile.php?status=error");
            exit;
        }

        // Prepare update data
        $updateData = [
            "name" => $name,
            "email" => $email
        ];

        // Update password only if provided
        if(!empty($password)){
            $updateData["password"] = password_hash($password, PASSWORD_DEFAULT);
        }

        try {
            // Update Firebase
            $result = $rdb->update("/user", $user_id, $updateData);

            if($result){
                // Optional: update session
                $_SESSION['email'] = $email;
                $_SESSION['user_name'] = $name;

                header("Location: your_profile.php?status=success");
                exit;
            } else {
                header("Location: your_profile.php?status=error");
                exit;
            }

        } catch(Exception $e){
            header("Location: your_profile.php?status=error");
            exit;
        }
        break;
}