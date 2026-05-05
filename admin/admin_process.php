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

?>