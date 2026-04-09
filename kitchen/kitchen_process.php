<?php
session_start();

include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

$rdb = new firebaseRDB($databaseURL);
$action = $_POST['action'] ?? '';

switch($action){

    // ===== SIGNUP =====
    case 'signup':
        $full_name = $_POST['full_name'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        if(!$email || !$password){
            die("Email and password required");
        }

        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $kitchen_data = [
            'full_name' => $full_name,
            'email' => $email,
            'password' => $password_hash,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $rdb->insert("/kitchen", $kitchen_data);

        header("Location: kitchen_login.php");
        exit;

    // ===== LOGIN =====
    case 'login':
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        $all_kitchen = json_decode($rdb->retrieve("/kitchen"), true);

        if(is_array($all_kitchen)){
            foreach($all_kitchen as $id => $k){
                if(($k['email'] ?? '') === $email && password_verify($password, $k['password'] ?? '')){
                    
                    $_SESSION['kitchen_email'] = $email;

                    header("Location: kitchen_index.php");
                    exit;
                }
            }
        }

        die("Invalid credentials");

        // ===== ✅ UPDATE ORDER STATUS (FROM BUTTONS) =====
    case 'update_status':

        if(!isset($_SESSION['kitchen_email'])){
            header("Location: kitchen_login.php");
            exit;
        }

        $order_id = $_POST['order_id'] ?? '';
        $status = $_POST['status'] ?? '';

        if($order_id && $status){

            // ✅ FIX: correct update format (3 arguments)
            $rdb->update("/orders", $order_id, [
                "kitchen_status" => $status
            ]);

        }

        header("Location: kitchen_index.php");
        exit;
}
?>