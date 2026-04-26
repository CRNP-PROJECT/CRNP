<?php
include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

$rdb = new firebaseRDB($databaseURL);

/* ================= FETCH DATA ================= */

$orders = json_decode($rdb->retrieve("/orders"), true) ?? [];
$kitchenData = json_decode($rdb->retrieve("/kitchen_history"), true) ?? [];
$bookings = json_decode($rdb->retrieve("/cashier_bookinghistory"), true) ?? [];
$users = json_decode($rdb->retrieve("/user"), true) ?? [];

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

/* ================= BOOKING PRICES ================= */

$bookingPrices = [
    'table' => 100,
    'chair' => 10,
    'skirting' => 150
];

$bookingsPerDay = [];
$today = date('Y-m-d');
$productSales = [];

/* ================= ORDERS ================= */

foreach($orders as $order){

    if(!is_array($order)) continue;

    $status = strtolower($order['status'] ?? 'pending');
    $ordersStatus[$status] = ($ordersStatus[$status] ?? 0) + 1;

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

foreach($kitchenData as $k){
    if(!is_array($k)) continue;
    $kitchenStatus['done']++;
    $kpis['totalRevenue'] += floatval($k['total'] ?? 0);
}

/* ================= BOOKINGS ================= */

foreach($bookings as $b){

    if(!is_array($b)) continue;

    $status = strtolower($b['final_status'] ?? 'pending');
    $bookingsStatus[$status] = ($bookingsStatus[$status] ?? 0) + 1;

    $date = $b['cashier_action_time'] ?? $b['created_at'] ?? '';
    $dateKey = substr($date, 0, 10);

    if($dateKey){
        $bookingsPerDay[$dateKey] = ($bookingsPerDay[$dateKey] ?? 0) + 1;
    }

    $items = $b['items'] ?? $b['booking_items'] ?? $b['rent_items'] ?? [];

    if(is_array($items)){

        foreach($items as $item){

            if(!is_array($item)) continue;

            $name = strtolower($item['name'] ?? '');
            $qty = intval($item['qty'] ?? 0);

            foreach($bookingPrices as $key => $price){

                if(strpos($name, $key) !== false){
                    $kpis['bookingRevenue'] += $price * $qty;
                    $kpis['totalRevenue'] += $price * $qty;
                }
            }
        }
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