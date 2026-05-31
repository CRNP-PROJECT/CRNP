<?php
include(__DIR__ . "/../config.php");
require_once(__DIR__ . "/../firebaseRDB.php");

$rdb = new firebaseRDB($databaseURL);



/* ================= DATE FILTER ================= */
$selectedDate = $_GET['date'] ?? null;

/* ================= PRODUCTS ================= */

$products = json_decode($rdb->retrieve("/products"), true) ?? [];

$productMap = [];
foreach ($products as $id => $p) {
    $productMap[$id] = $p;
}

/* ================= IMAGE UPLOAD ================= */

function uploadImage($file){

    $dir = __DIR__ . "/item/";

    if(!file_exists($dir)){
        mkdir($dir, 0777, true);
    }

    $fileName = time() . "_" . basename($file["name"]);
    $target = $dir . $fileName;

    if(move_uploaded_file($file["tmp_name"], $target)){
        return "item/" . $fileName;
    }

    return "";
}

/* ================= ADD / UPDATE / DELETE ================= */

if (isset($_POST['add_rent_item'])) {

    $name = trim($_POST['name'] ?? '');
    $price = floatval($_POST['price'] ?? 0);

    $image = "";
    if(!empty($_FILES['image']['name'])){
        $image = uploadImage($_FILES['image']);
    }

    if ($name !== "" && $price > 0) {

        $rdb->insert("/rent_items", [
            "name" => strtolower($name),
            "display_name" => ucfirst($name),
            "price" => $price,
            "image" => $image
        ]);
    }

    header("Location: booking_add.php");
    exit;
}

if (isset($_POST['update_rent_item'])) {

    $id = $_POST['id'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $price = floatval($_POST['price'] ?? 0);

    $image = $_POST['old_image'] ?? "";

    if(!empty($_FILES['image']['name'])){
        $image = uploadImage($_FILES['image']);
    }

    if ($id !== "" && $name !== "" && $price > 0) {

        $rdb->update("rent_items", $id, [
            "name" => strtolower($name),
            "display_name" => ucfirst($name),
            "price" => $price,
            "image" => $image
        ]);
    }

    header("Location: booking_add.php");
    exit;
}

if (isset($_GET['delete_rent_item'])) {

    $id = $_GET['delete_rent_item'] ?? '';

    if ($id !== "") {
        $rdb->delete("rent_items", $id);
    }

    header("Location: booking_add.php");
    exit;
}
/* ================= CREATE CASHIER ACCOUNT ================= */

if(isset($_POST['create_cashier'])){

    if(!isset($_SESSION['admin_email'])){
        die("Unauthorized");
    }

    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if($full_name == '' || $email == '' || $password == ''){
        die("All fields are required.");
    }

    /* CHECK DUPLICATE EMAIL */

    $cashiers = json_decode($rdb->retrieve("/cashiers"), true) ?? [];

    foreach($cashiers as $c){

        if(
            strtolower($c['email'] ?? '') === strtolower($email)
        ){
            die("Email already exists.");
        }
    }

    /* INSERT CASHIER */

    $rdb->insert("/cashiers", [

        "full_name" => $full_name,
        "email" => $email,
        "password" => password_hash($password, PASSWORD_DEFAULT),
        "created_at" => date('Y-m-d H:i:s')

    ]);

    header("Location: create_cashier.php?success=1");
    exit;
}
/* ================= CREATE KITCHEN ACCOUNT ================= */

if(isset($_POST['action']) && $_POST['action'] == "admin_create_kitchen"){

    if(!isset($_SESSION['admin_email'])){
        die("Unauthorized");
    }

    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if($full_name == '' || $email == '' || $password == ''){
        die("All fields are required.");
    }

    /* CHECK DUPLICATE EMAIL */
    $kitchens = json_decode($rdb->retrieve("/kitchen"), true) ?? [];

    foreach($kitchens as $k){
        if(strtolower($k['email'] ?? '') === strtolower($email)){
            die("Email already exists.");
        }
    }

    /* INSERT */
    $rdb->insert("/kitchen", [
        "full_name" => $full_name,
        "email" => $email,
        "password" => password_hash($password, PASSWORD_DEFAULT),
        "created_at" => date('Y-m-d H:i:s')
    ]);

    header("Location: create_kitchen.php?success=1");
    exit;
}
if(isset($_POST['return_booking'])){



    include(__DIR__ . "/../config.php");

    require_once(__DIR__ . "/../firebaseRDB.php");



    $rdb = new firebaseRDB($databaseURL);



    $bookingId = $_POST['booking_id'] ?? '';



    if($bookingId == ''){

        die("Missing booking ID");

    }



    $bookings = json_decode($rdb->retrieve("/bookings"), true) ?? [];

    $rent_items = json_decode($rdb->retrieve("/rent_items"), true) ?? [];



    if(!isset($bookings[$bookingId])){

        die("Booking not found");

    }



    $booking = $bookings[$bookingId];



    /* ================= RESTORE STOCK ================= */

    if(!empty($booking['items']) && is_array($booking['items'])){



        foreach($booking['items'] as $item){



            $qty = intval($item['qty'] ?? 0);

            $name = strtolower(trim($item['name'] ?? ''));



            if($qty <= 0 || $name == '') continue;



            foreach($rent_items as $rid => $ritem){



                if(strtolower(trim($ritem['name'] ?? '')) === $name){     

                    $current = intval($ritem['quantity'] ?? 0);



                    $rdb->update("rent_items", $rid, [

                        "quantity" => $current + $qty

                    ]);



                    break;

                }

            }

        }

    }



    /* ================= UPDATE BOOKING ================= */

    $rdb->update("bookings", $bookingId, [

        "status" => "returned",

        "returned_at" => date("Y-m-d H:i:s"),

        "returned_by" => $_SESSION['admin_id'] ?? 'admin'

    ]);



    header("Location: booking_reserve.php?success=returned");

    exit;

}


?>