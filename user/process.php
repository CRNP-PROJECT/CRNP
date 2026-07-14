<?php
session_start();

include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

class Process
{
    private $rdb;
    private $action;

    public function __construct($databaseURL)
    {
        $this->rdb = new firebaseRDB($databaseURL);

        date_default_timezone_set("Asia/Manila");

        if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        $this->action = $_POST['action'] ?? $_GET['action'] ?? '';
    }

    public function handle()
    {
        switch ($this->action) {

            case 'cancel_booking':
                $this->cancelBooking();
                break;

            case 'cancel_order':
                $this->cancelOrder();
                break;

            case 'add_to_cart':
                $this->addToCart();
                break;

            case 'buy_now':
                $this->buyNow();
                break;

            case 'update_cart':
                $this->updateCart();
                break;

            case 'remove_cart':
                $this->removeCart();
                break;

            case 'confirm_checkout':
                $this->confirmCheckout();
                break;

            case 'booking':
                $this->booking();
                break;

            case 'update_profile':
                $this->updateProfile();
                break;

            default:
                header("Location: index.php");
                exit;
        }
    }

    /* ==========================================
       CANCEL BOOKING
    ========================================== */

    private function cancelBooking()
{
    if (!isset($_SESSION['email'])) {
        header("Location: login.php");
        exit;
    }

    $bookingId = $_POST['booking_id'] ?? '';
    $reason = trim($_POST['cancel_reason'] ?? '');

    if (empty($bookingId)) {
        die("Invalid booking.");
    }

    // ================= GET BOOKING =================
    $booking = json_decode(
        $this->rdb->retrieve("/bookings/" . $bookingId),
        true
    );

    if (!$booking) {
        die("Booking not found.");
    }

    // ================= CHECK OWNER =================
    $bookingEmail =
        $booking['email']
        ?? $booking['user_email']
        ?? '';

    if ($bookingEmail != $_SESSION['email']) {
        die("Unauthorized.");
    }

    // ================= CHECK STATUS =================
    $status = strtolower($booking['status'] ?? '');

    if (in_array($status, ['done', 'returned', 'rejected', 'cancelled'])) {
        die("Booking can no longer be cancelled.");
    }

    // ================= RESTORE RENT ITEM STOCK =================
    $rent_items = json_decode(
        $this->rdb->retrieve("/rent_items"),
        true
    ) ?? [];

    if (!empty($booking['items']) && is_array($booking['items'])) {

        foreach ($booking['items'] as $id => $item) {

            $qty = intval($item['qty'] ?? 0);

            if ($qty <= 0) {
                continue;
            }

            $current = intval(
                ($rent_items[$id]['quantity'] ?? 0)
            );

            $this->rdb->update(
                "/rent_items",
                $id,
                [
                    "quantity" => $current + $qty
                ]
            );
        }
    }

    // ================= UPDATE BOOKING =================
    $booking['status'] = 'cancelled';
    $booking['cancel_reason'] = $reason;
    $booking['cancelled_at'] = date('Y-m-d H:i:s');

    $this->rdb->update(
        "/bookings",
        $bookingId,
        $booking
    );

    header("Location: your_orders.php?type=bookings&status=cancelled");
    exit;
}

    /* ==========================================
       CANCEL ORDER
    ========================================== */

    private function cancelOrder()
    {
        $orderId = $_POST['order_id'] ?? '';
        $reason = trim($_POST['cancel_reason'] ?? '');

        if (empty($orderId)) {
            die("Order ID missing.");
        }

        $order = json_decode(
            $this->rdb->retrieve("/orders/$orderId"),
            true
        );

        if (!$order) {
            die("Order not found.");
        }

        if (($order['user_email'] ?? '') != ($_SESSION['email'] ?? '')) {
            die("Unauthorized.");
        }

        $status = strtolower($order['status'] ?? '');
        $kitchenStatus = strtolower($order['kitchen_status'] ?? '');

        if (
            !in_array($status, ['pending', 'accepted']) ||
            in_array($kitchenStatus, ['preparing', 'ready'])
        ) {
            die("Order can no longer be cancelled.");
        }

        $order['status'] = 'cancelled';
        $order['cancel_reason'] = $reason;
        $order['cancelled_at'] = date('Y-m-d H:i:s');

        $this->rdb->update(
            "/orders",
            $orderId,
            $order
        );

        header("Location: your_orders.php?type=orders");
        exit;
    }
        /* ==========================================
       ADD TO CART
    ========================================== */

    private function addToCart()
    {
        $product_id = $_POST['product_id'] ?? '';

        if ($product_id == "") {
            echo "error";
            exit;
        }

        if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        if (isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id]++;
        } else {
            $_SESSION['cart'][$product_id] = 1;
        }

        echo "success";
        exit;
    }

    /* ==========================================
       BUY NOW
    ========================================== */

    private function buyNow()
    {
        $product_id = $_POST['product_id']
            ?? $_GET['product_id']
            ?? '';

        if ($product_id == "") {
            header("Location: products.php");
            exit;
        }

        $_SESSION['cart'] = [];
        $_SESSION['cart'][$product_id] = 1;

        header("Location: checkout.php");
        exit;
    }

    /* ==========================================
       UPDATE CART
    ========================================== */

    private function updateCart()
    {
        $product_id = $_POST['product_id'] ?? '';
        $qty = intval($_POST['quantity'] ?? 1);

        if ($product_id != "" && $qty > 0) {
            $_SESSION['cart'][$product_id] = $qty;
        }

        header("Location: cart.php");
        exit;
    }

    /* ==========================================
       REMOVE CART
    ========================================== */

    private function removeCart()
    {
        $id = $_GET['id'] ?? '';

        if ($id != "" && isset($_SESSION['cart'][$id])) {
            unset($_SESSION['cart'][$id]);
        }

        header("Location: cart.php");
        exit;
    }

    /* ==========================================
       CHECKOUT
    ========================================== */

    private function confirmCheckout()
    {
        if (!isset($_SESSION['email'])) {
            header("Location: user_login.php");
            exit;
        }

        if (empty($_SESSION['cart'])) {
            header("Location: cart.php");
            exit;
        }

        $payment_method = $_POST['payment_method'] ?? 'counter';
        $gcash_number = $_POST['gcash_number'] ?? '';
        $gcash_receipt = null;

        // GCASH UPLOAD
        if ($payment_method === "gcash") {

            if (!empty($_FILES['gcash_receipt']['name'])) {

                $uploadDir = __DIR__ . "/uploads/";

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $fileName = time() . "_" .
                    basename($_FILES["gcash_receipt"]["name"]);

                $targetFile = $uploadDir . $fileName;

                $fileType = strtolower(
                    pathinfo($targetFile, PATHINFO_EXTENSION)
                );

                $allowed = ['jpg','jpeg','png','webp'];

                if (!in_array($fileType, $allowed)) {
                    die("Invalid file type.");
                }

                if (
                    move_uploaded_file(
                        $_FILES["gcash_receipt"]["tmp_name"],
                        $targetFile
                    )
                ) {

                    $gcash_receipt = "uploads/" . $fileName;

                } else {

                    die("Upload failed.");
                }

            } else {

                die("GCash receipt is required.");
            }
        }

        $cart = $_SESSION['cart'];
        $total = 0;
        $products_detail = [];

        foreach ($cart as $id => $qty) {

            $product = json_decode(
                $this->rdb->retrieve("/products/$id"),
                true
            );

            if ($product) {

                $price = floatval($product['price']);
                $subtotal = $price * $qty;

                $total += $subtotal;

                $products_detail[$id] = [
                    'category' => $product['category'] ?? 'unknown',
                    'name' => $product['name'] ?? '',
                    'price' => $price,
                    'qty' => $qty,
                    'subtotal' => $subtotal
                ];
            }
        }

        $appointment_time = $_POST['appointment_time'] ?? '';

                $order_data = [

            'user_email' => $_SESSION['email'],
            'full_name' => $_POST['full_name'] ?? '',
            'contact_number' => $_POST['contact_number'] ?? '',
            'num_people' => $_POST['num_people'] ?? '',

            'appointment_time' => $appointment_time,
            'appointment_timestamp' => strtotime($appointment_time),

            'total' => $total,

            'products' => $products_detail,

            'payment_method' => $payment_method,
            'gcash_number' => $gcash_number,
            'gcash_receipt' => $gcash_receipt,

            'payment_status' =>
                ($payment_method === "gcash")
                    ? "pending_verification"
                    : "no_payment_required",

            'payment_verified' =>
                ($payment_method === "counter")
                    ? true
                    : false,

            'status' => 'pending',

            'created_at' => date("Y-m-d H:i:s"),
            'date' => date("Y-m-d"),
            'timestamp' => time()

        ];

        $this->rdb->insert("/orders", $order_data);

        unset($_SESSION['cart']);

        header("Location: checkout_success.php");
        exit;
    }

    /* ==========================================
       BOOKING
    ========================================== */

    private function booking()
    {

        if (!isset($_SESSION['email'])) {
            header("Location: user_login.php");
            exit;
        }

        $payment_method = $_POST['payment_method'] ?? 'counter';
        $gcash_number = $_POST['gcash_number'] ?? '';
        $booking_receipt = null;

        $rent_items_raw = json_decode(
            $this->rdb->retrieve("/rent_items"),
            true
        ) ?? [];

        $selected_items = $_POST['rent_items'] ?? [];

        $booking_total = 0;
        $booking_details = [];

        foreach ($selected_items as $id => $qty) {

            $qty = intval($qty);

            if ($qty <= 0) {
                continue;
            }

            if (isset($rent_items_raw[$id])) {

                $item = $rent_items_raw[$id];

                $available = intval($item['quantity'] ?? 0);

                if ($qty > $available) {

                    die(
                        ($item['display_name']
                        ?? $item['name']
                        ?? 'Item')
                        . " only has "
                        . $available
                        . " available."
                    );
                }

                $price = floatval($item['price'] ?? 0);

                $subtotal = $price * $qty;

                $booking_total += $subtotal;

                $booking_details[$id] = [

                    'name' =>
                        $item['display_name']
                        ?? $item['name']
                        ?? 'Unnamed Item',

                    'price' => $price,
                    'qty' => $qty,
                    'subtotal' => $subtotal

                ];
            }
        }

        /* =============================
           GCASH UPLOAD
        ============================== */

        if ($payment_method === "gcash") {

            if (!empty($_FILES['gcash_receipt']['name'])) {

                $uploadDir = __DIR__ . "/bookings/";

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $fileName =
                    time() . "_"
                    . basename($_FILES["gcash_receipt"]["name"]);

                $targetFile = $uploadDir . $fileName;

                $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
                $allowed = ['jpg','jpeg','png','webp'];
                if (!in_array($fileType, $allowed)) {
                    die("Invalid file type.");
                }
                if ($_FILES["gcash_receipt"]["size"] > 5 * 1024 * 1024) {
                    die("File too large (max 5MB).");
                }

                if (
                    move_uploaded_file(
                        $_FILES["gcash_receipt"]["tmp_name"],
                        $targetFile
                    )
                ) {

                    $booking_receipt =
                        "bookings/" . $fileName;

                } else {

                    die("Upload failed.");
                }

            } else {

                die("GCash receipt is required.");
            }
        }
                /* =============================
           SAVE BOOKING
        ============================== */

        $booking_data = [

            'user_email' => $_SESSION['email'],
            'full_name' => $_POST['full_name'] ?? '',
            'contact_number' => $_POST['contact_number'] ?? '',
            'address' => $_POST['address'] ?? '',
            'appointment_time' => $_POST['appointment_time'] ?? '',
            'return_time' => $_POST['return_time'] ?? '',

            'items' => $booking_details,
            'booking_total' => $booking_total,

            'payment_method' => $payment_method,
            'gcash_number' => $gcash_number,
            'gcash_receipt' => $booking_receipt,

            'payment_status' =>
                ($payment_method === "gcash")
                    ? "pending_verification"
                    : "no_payment_required",

            'payment_verified' =>
                ($payment_method === "counter")
                    ? true
                    : false,

            'status' => 'pending',
            'created_at' => date("M d, Y h:i A")

        ];

        $this->rdb->insert("/bookings", $booking_data);

        /* =============================
           DEDUCT RENT ITEM STOCK
        ============================== */

        foreach ($selected_items as $id => $qty) {

            $qty = intval($qty);

            if ($qty <= 0) {
                continue;
            }

            if (isset($rent_items_raw[$id])) {

                $item = $rent_items_raw[$id];

                $available = intval($item['quantity'] ?? 0);

                $new_quantity = $available - $qty;

                $this->rdb->update(
                    "rent_items",
                    $id,
                    [
                        "quantity" => $new_quantity
                    ]
                );
            }
        }

        header("Location: booking_success.php");
        exit;
    }

    /* ==========================================
       UPDATE PROFILE
    ========================================== */

    private function updateProfile()
    {

        if (!isset($_SESSION['user_id'])) {
            header("Location: user_login.php");
            exit;
        }

        $user_id = $_SESSION['user_id'];

        $existing = json_decode(
            $this->rdb->retrieve("/user/" . $user_id),
            true
        ) ?? [];

        $name = $_POST['name']
            ?? $existing['name']
            ?? '';

        $email = $_POST['email']
            ?? $existing['email']
            ?? '';

        $password = $_POST['password'] ?? '';

        $profile_image =
            $existing['profile_image']
            ?? null;

        if (!empty($_FILES['profile_image']['name'])) {

            $uploadDir = __DIR__ . "/../profile/";

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $fileName =
                time() . "_"
                . basename($_FILES["profile_image"]["name"]);

            $targetFile = $uploadDir . $fileName;

            $fileType = strtolower(
                pathinfo(
                    $targetFile,
                    PATHINFO_EXTENSION
                )
            );

            $allowed = [
                'jpg',
                'jpeg',
                'png',
                'webp'
            ];

            if (in_array($fileType, $allowed)) {

                if (
                    move_uploaded_file(
                        $_FILES["profile_image"]["tmp_name"],
                        $targetFile
                    )
                ) {

                    $profile_image =
                        "../profile/" . $fileName;

                } else {

                    die("Image upload failed.");
                }

            } else {

                die("Invalid image format.");
            }
        }

        $update_data = [

            'name' => $name,
            'email' => $email,
            'profile_image' => $profile_image

        ];

        if (!empty($password)) {
            if (strlen($password) < 8) {
                die("Password must be at least 8 characters.");
            }
            $update_data['password'] =
                password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );
        }

        $this->rdb->update(
            "user",
            $user_id,
            $update_data
        );

        $_SESSION['username'] = $name;

        header("Location: your_profile.php?status=success");
        exit;
    }
    }

$process = new Process($databaseURL);
$process->handle();

?>