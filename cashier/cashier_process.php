<?php
session_start();

include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

class CashierProcess
{
    private $rdb;
    private $action;

    public function __construct($databaseURL)
    {
        $this->rdb = new firebaseRDB($databaseURL);
        $this->action = $_POST['action'] ?? '';
    }

    public function handle()
    {
        // Guard all actions except login
        if (!isset($_SESSION['cashier_email']) && $this->action !== 'login') {
            header("Location: cashier_login.php");
            exit;
        }
        switch ($this->action) {

            case 'cancel_order':
                $this->cancelOrder();
                break;

            case 'login':
                $this->login();
                break;

            case 'logout':
                $this->logout();
                break;

            case 'update_status':
                $this->updateStatus();
                break;

            case 'mark_paid':
                $this->markPaid();
                break;

            case 'mark_not_paid':
                $this->markNotPaid();
                break;

            case 'update_booking_status':
                $this->updateBookingStatus();
                break;

            case 'mark_booking_paid':
                $this->markBookingPaid();
                break;

            default:
                header("Location: cashier_login.php");
                exit;
        }
    }

    /* ================= CANCEL ORDER ================= */

    private function cancelOrder()
    {
        $firebase_key = $_POST['order_id'] ?? '';
        $cancel_note = trim($_POST['cancel_note'] ?? '');

        if (empty($firebase_key)) {
            die("No order ID received");
        }

        $order = json_decode(
            $this->rdb->retrieve("/orders/" . $firebase_key),
            true
        );

        if (!$order) {
            die("Order not found");
        }

        $order['status'] = 'cashier_cancelled';
        $order['cashier_name'] = $_SESSION['cashier_name'] ?? '';
        $order['cashier_email'] = $_SESSION['cashier_email'] ?? '';

        $order['cancelled_by'] =
            ($_SESSION['cashier_name'] ?? '') .
            ' (' .
            ($_SESSION['cashier_email'] ?? '') .
            ')';

        $order['cancel_note'] = $cancel_note;
        $order['cancelled_at'] = date('Y-m-d H:i:s');

        $this->rdb->update(
            "/orders",
            $firebase_key,
            $order
        );

        header("Location: cashier_orderHistory.php?success=cancelled");
        exit;
    }

    /* ================= LOGIN ================= */

    private function login()
    {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        $cashiers = json_decode(
            $this->rdb->retrieve("/cashiers"),
            true
        ) ?? [];

        foreach ($cashiers as $c) {

            if (
                strtolower($c['email'] ?? '') === strtolower($email) &&
                password_verify($password, $c['password'] ?? '')
            ) {

                session_regenerate_id(true);
                $_SESSION['cashier_email'] = $email;
                $_SESSION['cashier_name'] = $c['full_name'];

                header("Location: cashier_index.php");
                exit;
            }
        }

        die("Invalid email or password");
    }

    /* ================= LOGOUT ================= */

    private function logout()
    {
        session_destroy();

        header("Location: cashier_login.php");
        exit;
    }

    /* ================= UPDATE ORDER STATUS ================= */

    private function updateStatus()
    {
        $order_id = $_POST['order_id'] ?? '';
        $status = strtolower(trim($_POST['status'] ?? ''));

        if (!$order_id || !$status) {
            header("Location: view_orders.php");
            exit;
        }

        $order = json_decode(
            $this->rdb->retrieve("/orders/$order_id"),
            true
        );

        if (!$order) {
            header("Location: view_orders.php");
            exit;
        }

        $order['status'] = $status;
        $order['final_status'] = $status;
        $order['cashier_action_time'] = date("Y-m-d H:i:s");

        if ($status === "rejected") {

            $order['payment_status'] = null;
            $order['payment_verified'] = false;
            $order['paid_at'] = null;
        }

        $this->rdb->update("/orders", $order_id, $order);

        header("Location: view_orders.php");
        exit;
    }

    /* ================= MARK ORDER PAID ================= */

    private function markPaid()
    {
        $order_id = $_POST['order_id'] ?? '';

        if (!$order_id) {
            header("Location: payment_status.php");
            exit;
        }

        $order = json_decode(
            $this->rdb->retrieve("/orders/$order_id"),
            true
        );

        if ($order && ($order['status'] ?? '') !== "rejected") {

            $order['payment_status'] = "paid";
            $order['payment_verified'] = true;
            $order['paid_at'] = date("Y-m-d H:i:s");

            $this->rdb->update("/orders", $order_id, $order);
        }

        header("Location: payment_status.php");
        exit;
    }

    /* ================= MARK ORDER NOT PAID ================= */

    private function markNotPaid()
    {
        $order_id = $_POST['order_id'] ?? '';

        if (!$order_id) {
            header("Location: payment_status.php");
            exit;
        }

        $order = json_decode(
            $this->rdb->retrieve("/orders/$order_id"),
            true
        );

        if ($order && ($order['status'] ?? '') !== "rejected") {

            $order['payment_status'] = "not_paid";
            $order['payment_verified'] = false;

            $this->rdb->update("/orders", $order_id, $order);
        }

        header("Location: payment_status.php");
        exit;
    }

    private function updateBookingStatus()
{
    $booking_id = $_POST['booking_id'] ?? '';
    $status = strtolower(trim($_POST['status'] ?? ''));

    if (!$booking_id || !$status) {
        header("Location: view_bookings.php");
        exit;
    }

    // ================= GET BOOKING =================
    $booking = json_decode(
        $this->rdb->retrieve("/bookings/$booking_id"),
        true
    );

    if (!$booking) {
        header("Location: view_bookings.php");
        exit;
    }

    // ================= PREVENT DOUBLE RESTORE =================
    $currentStatus = strtolower($booking['status'] ?? '');

    if (
        $status === "rejected" &&
        !in_array($currentStatus, ['rejected', 'cancelled', 'returned'])
    ) {

        // Reset payment
        $booking['payment_status'] = null;
        $booking['payment_verified'] = false;

        // ================= RESTORE RENT ITEM STOCK =================
        $rent_items = json_decode(
            $this->rdb->retrieve("/rent_items"),
            true
        ) ?? [];

        if (!empty($booking['items']) && is_array($booking['items'])) {

            foreach ($booking['items'] as $item) {

                $qty = intval($item['qty'] ?? 0);
                $name = strtolower(trim($item['name'] ?? ''));

                if ($qty <= 0 || $name == '') {
                    continue;
                }

                foreach ($rent_items as $rid => $ritem) {

                    if (
                        strtolower(trim($ritem['name'] ?? '')) === $name
                    ) {

                        $current = intval($ritem['quantity'] ?? 0);

                        $this->rdb->update(
                            "/rent_items",
                            $rid,
                            [
                                "quantity" => $current + $qty
                            ]
                        );

                        break;
                    }
                }
            }
        }
    }

    // ================= UPDATE BOOKING =================
    $booking['status'] = $status;
    $booking['updated_at'] = date("Y-m-d H:i:s");

    $this->rdb->update(
        "/bookings",
        $booking_id,
        $booking
    );

    header("Location: view_bookings.php");
    exit;
}
    /* ================= MARK BOOKING PAID ================= */

    private function markBookingPaid()
    {
        $booking_id = $_POST['booking_id'] ?? '';

        if (!$booking_id) {
            header("Location: booking_payment.php");
            exit;
        }

        $booking = json_decode(
            $this->rdb->retrieve("/bookings/$booking_id"),
            true
        );

        if ($booking) {

            $booking['payment_status'] = "paid";
            $booking['payment_verified'] = true;
            $booking['paid_at'] = date("Y-m-d H:i:s");

            $this->rdb->update("/bookings", $booking_id, $booking);
        }

        header("Location: booking_payment.php");
        exit;
    }
}

$cashier = new CashierProcess($databaseURL);
$cashier->handle();

?>