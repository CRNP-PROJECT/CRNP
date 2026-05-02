<?php
include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

$rdb = new firebaseRDB($databaseURL);

/* ================= FETCH DATA ================= */

$orders = json_decode($rdb->retrieve("/orders"), true) ?? [];
$kitchenData = json_decode($rdb->retrieve("/kitchen_history"), true) ?? [];
$bookings = json_decode($rdb->retrieve("/cashier_bookinghistory"), true) ?? [];
$users = json_decode($rdb->retrieve("/user"), true) ?? [];
$rentItems = json_decode($rdb->retrieve("/rent_items"), true) ?? [];

/* ================= RENT ITEMS ================= */

$rentItems = json_decode($rdb->retrieve("/rent_items"), true) ?? [];

/* ================= IMAGE UPLOAD (ADMIN/ITEM) ================= */

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

/* ================= ADD RENT ITEM ================= */

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

/* ================= UPDATE RENT ITEM ================= */

if (isset($_POST['update_rent_item'])) {

    $id = $_POST['id'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $price = floatval($_POST['price'] ?? 0);

    $image = $_POST['old_image'] ?? "";

    if(!empty($_FILES['image']['name'])){
        $image = uploadImage($_FILES['image']);
    }

    if ($id !== "" && $name !== "" && $price > 0) {

        $rdb->update(
            "rent_items",
            $id,
            [
                "name" => strtolower($name),
                "display_name" => ucfirst($name),
                "price" => $price,
                "image" => $image
            ]
        );
    }

    header("Location: booking_add.php");
    exit;
}

/* ================= DELETE RENT ITEM ================= */

if (isset($_GET['delete_rent_item'])) {

    $id = $_GET['delete_rent_item'] ?? '';

    if ($id !== "") {
        $rdb->delete("rent_items", $id);
    }

    header("Location: booking_add.php");
    exit;
}



/* ================= KPI ================= */

$kpis = [
    'todayRevenue' => 0,
    'totalRevenue' => 0,
    'totalSales' => 0,
    'totalUsers' => count($users),
    'totalBookings' => count($bookings),
    'bookingRevenue' => 0
];

/* ================= STATUS ================= */

$ordersStatus = ['pending'=>0,'accepted'=>0,'rejected'=>0,'done'=>0];
$kitchenStatus = ['preparing'=>0,'ready'=>0,'done'=>0];
$bookingsStatus = ['pending'=>0,'accepted'=>0,'rejected'=>0];

$bookingsPerDay = [];
$productSales = [];

$today = date('Y-m-d');

/* ================= ORDERS ================= */

foreach($orders as $order){

    if(!is_array($order)) continue;

    $status = strtolower(trim($order['status'] ?? 'pending'));

    if(isset($ordersStatus[$status])){
        $ordersStatus[$status]++;
    } else {
        $ordersStatus['pending']++;
    }

    $total = floatval($order['total'] ?? 0);
    $kpis['totalRevenue'] += $total;
    $kpis['totalSales']++;

    if(substr($order['created_at'] ?? '', 0, 10) === $today){
        $kpis['todayRevenue'] += $total;
    }

    foreach($order['products'] ?? [] as $p){
        $name = $p['name'] ?? 'Unknown';
        $qty = intval($p['qty'] ?? 0);

        $productSales[$name] = ($productSales[$name] ?? 0) + $qty;
    }
}

/* ================= KITCHEN ================= */

foreach($orders as $order){

    if(!is_array($order)) continue;

    $status = strtolower(trim($order['kitchen_status'] ?? ''));

    if($status === 'preparing'){
        $kitchenStatus['preparing']++;
    }
    elseif($status === 'ready'){
        $kitchenStatus['ready']++;
    }
    elseif(in_array($status, ['done','completed','finished'])){
        $kitchenStatus['done']++;
    }
}

/* ================= BOOKINGS (FIXED - USE /bookings ONLY) ================= */

$bookingsStatus = ['pending'=>0,'accepted'=>0,'rejected'=>0];
$bookingsPerDay = [];

/* 🔥 ALL BOOKINGS COME FROM /bookings */
$allBookings = json_decode($rdb->retrieve("/bookings"), true) ?? [];

foreach($allBookings as $b){

    if(!is_array($b)) continue;

    $status = strtolower(trim($b['status'] ?? 'pending'));

    if($status === 'pending'){
        $bookingsStatus['pending']++;
    }
    elseif($status === 'accepted'){
        $bookingsStatus['accepted']++;
    }
    elseif($status === 'rejected'){
        $bookingsStatus['rejected']++;
    }
    else {
        $bookingsStatus['pending']++; // fallback
    }

    $date = $b['created_at'] ?? '';
    $dateKey = substr($date, 0, 10);

    if($dateKey){
        $bookingsPerDay[$dateKey] = ($bookingsPerDay[$dateKey] ?? 0) + 1;
    }
}

/* ================= RETURN ================= */

return [
    'kpis' => $kpis,
    'ordersStatus' => $ordersStatus,
    'bookingsStatus' => $bookingsStatus,
    'kitchenStatus' => $kitchenStatus,
    'bestSelling' => $productSales,
    'bookingsPerDay' => $bookingsPerDay,
    'rentItems' => $rentItems
];
