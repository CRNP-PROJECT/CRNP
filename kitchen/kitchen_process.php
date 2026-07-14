<?php
session_start();

include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

class KitchenProcess
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
        switch ($this->action) {

            case 'login':
                $this->login();
                break;

            case 'update_status':
                $this->updateStatus();
                break;

            default:
                die("Invalid action");
        }
    }

    /* ================= LOGIN ================= */

    private function login()
    {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email == '' || $password == '') {
            die("All fields are required.");
        }

        $all_kitchen = json_decode(
            $this->rdb->retrieve("/kitchen"),
            true
        ) ?? [];

        foreach ($all_kitchen as $k) {

            if (
                strtolower($k['email'] ?? '') === strtolower($email) &&
                password_verify($password, $k['password'] ?? '')
            ) {
                session_regenerate_id(true);
                $_SESSION['kitchen_email'] = $email;
                $_SESSION['kitchen_name'] = $k['full_name'] ?? '';
                $_SESSION['role'] = "kitchen";

                header("Location: kitchen_index.php");
                exit;
            }
        }

        header("Location: kitchen_login.php?error=invalid");
        exit;
    }

    /* ================= UPDATE ORDER STATUS ================= */

    private function updateStatus()
    {
        if (!isset($_SESSION['kitchen_email'])) {
            header("Location: kitchen_login.php");
            exit;
        }

        $order_id = $_POST['order_id'] ?? '';
        $status = $_POST['status'] ?? '';

        if (!$order_id || !$status) {
            die("Missing data");
        }

        $validStatuses = [
            'accepted',
            'preparing',
            'ready',
            'done'
        ];

        if (!in_array($status, $validStatuses)) {
            die("Invalid kitchen status");
        }

        /* ================= GET ORDER ================= */

        $order = json_decode(
            $this->rdb->retrieve("/orders/$order_id"),
            true
        );

        if (!$order) {
            die("Order not found");
        }

        $now = date("Y-m-d H:i:s");

        /* ================= UPDATE ORDER ================= */

        $updateOrder = [

            "kitchen_status" => $status,
            "kitchen_action_time" => $now

        ];

        $this->rdb->update(
            "/orders",
            $order_id,
            $updateOrder
        );

        /* ================= UPDATE HISTORY ================= */

        $historyData = [

            "order_id" => $order_id,
            "full_name" => $order['full_name'] ?? 'N/A',
            "table_number" => $order['table_number'] ?? '',
            "payment_method" => $order['payment_method'] ?? '',
            "products" => $order['products'] ?? [],
            "total" => $order['total'] ?? 0,

            "order_type" =>
                !empty($order['cashier'])
                    ? "WALK-IN"
                    : "ONLINE",

            "kitchen_status" => $status,
            "kitchen_action_time" => $now,

            "processed_by" =>
                $_SESSION['kitchen_email']

        ];

        $this->rdb->update(
            "/kitchen_history",
            $order_id,
            $historyData
        );

        header("Location: kitchen_index.php");
        exit;
    }
}

$kitchen = new KitchenProcess($databaseURL);
$kitchen->handle();

?>