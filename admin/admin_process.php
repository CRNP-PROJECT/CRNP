<?php
include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

$rdb = new firebaseRDB($databaseURL);

/* ================= FETCH DATA ================= */

$orders = json_decode($rdb->retrieve("/orders"), true) ?? [];
$kitchenData = json_decode($rdb->retrieve("/kitchen_history"), true) ?? [];
$bookings = json_decode($rdb->retrieve("/bookings"), true) ?? [];
$users = json_decode($rdb->retrieve("/user"), true) ?? [];
$rentItems = json_decode($rdb->retrieve("/rent_items"), true) ?? [];

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

/* ================= KPI ================= */

$kpis = [
    'todayRevenue' => 0,
    'totalRevenue' => 0,
    'totalSales' => 0,

    'todaySales' => 0,
    'selectedRevenue' => 0,
    'selectedSales' => 0,

    'totalUsers' => count($users),
    'totalBookings' => count($bookings),
    'bookingRevenue' => 0
];

/* ================= BOOKING EXTRA KPI ================= */

$bookingTotalSales = 0;
$bookingTotalOrders = 0;

$bookingRevenuePerDay = [];
$bookingOrdersPerDay = [];

/* ================= STATUS ================= */

$ordersStatus = ['pending'=>0,'accepted'=>0,'rejected'=>0,'done'=>0];
$kitchenStatus = ['preparing'=>0,'ready'=>0,'done'=>0];
$bookingsStatus = ['pending'=>0,'accepted'=>0,'rejected'=>0];

$bookingsPerDay = [];
$productSales = [];

$revenuePerDay = [];
$ordersByHour = [];

$ordersByDayOfWeek = [
    "Sun"=>0,"Mon"=>0,"Tue"=>0,"Wed"=>0,"Thu"=>0,"Fri"=>0,"Sat"=>0
];

$categorySales = [
    "Foods"=>0,
    "Drinks"=>0,
    "Beverages"=>0
];

$bookingItems = [];

$today = date('Y-m-d');

/* ================= ORDERS ================= */

foreach($orders as $order){

    if(!is_array($order)) continue;

    $orderDate = substr($order['created_at'] ?? '', 0, 10);

    if ($selectedDate && $orderDate !== $selectedDate) {
        continue;
    }

    $status = strtolower(trim($order['status'] ?? 'pending'));

    if(isset($ordersStatus[$status])){
        $ordersStatus[$status]++;
    } else {
        $ordersStatus['pending']++;
    }

    $total = floatval($order['total'] ?? 0);

    $kpis['totalRevenue'] += $total;
    $kpis['totalSales']++;

    if($orderDate === $today){
        $kpis['todayRevenue'] += $total;
        $kpis['todaySales']++;
    }

    if($selectedDate && $orderDate === $selectedDate){
        $kpis['selectedRevenue'] += $total;
        $kpis['selectedSales']++;
    }

    foreach($order['products'] ?? [] as $p){

        $name = $p['name'] ?? 'Unknown';
        $qty = intval($p['qty'] ?? 0);

        $productSales[$name] = ($productSales[$name] ?? 0) + $qty;

        $productId = $p['product_id'] ?? null;

        if ($productId && isset($productMap[$productId])) {
            $cat = strtolower(trim($productMap[$productId]['category'] ?? ''));
        } else {
            $cat = strtolower(trim($p['category'] ?? ''));
        }

        if (strpos($cat, 'food') !== false) {
            $categorySales['Foods'] += $qty;
        }
        elseif (strpos($cat, 'drink') !== false) {
            $categorySales['Drinks'] += $qty;
        }
        elseif (strpos($cat, 'beverage') !== false) {
            $categorySales['Beverages'] += $qty;
        }
    }

    if($orderDate){
        $revenuePerDay[$orderDate] = ($revenuePerDay[$orderDate] ?? 0) + $total;
    }

    $hour = date("H:00", strtotime($order['created_at'] ?? ''));
    if($hour){
        $ordersByHour[$hour] = ($ordersByHour[$hour] ?? 0) + 1;
    }

    $dow = date("D", strtotime($order['created_at'] ?? ''));
    if(isset($ordersByDayOfWeek[$dow])){
        $ordersByDayOfWeek[$dow]++;
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

/* ================= BOOKINGS (FIXED: ONLY ACCEPTED SALES) ================= */

foreach($bookings as $b){

    if(!is_array($b)) continue;

    $status = strtolower(trim($b['status'] ?? 'pending'));

    /* status chart still shows all */
    if(isset($bookingsStatus[$status])){
        $bookingsStatus[$status]++;
    } else {
        $bookingsStatus['pending']++;
    }

    /* ONLY ACCEPTED GO INTO SALES */
    if($status !== 'accepted') continue;

    $bookingTotalOrders++;

    $bookingTotal = floatval($b['booking_total'] ?? 0);
    $bookingTotalSales += $bookingTotal;

    $date = substr($b['created_at'] ?? '', 0, 10);

    if($date){
        $bookingRevenuePerDay[$date] =
            ($bookingRevenuePerDay[$date] ?? 0) + $bookingTotal;

        $bookingOrdersPerDay[$date] =
            ($bookingOrdersPerDay[$date] ?? 0) + 1;

        $bookingsPerDay[$date] =
            ($bookingsPerDay[$date] ?? 0) + 1;
    }

    if (!empty($b['items'])) {

        foreach($b['items'] as $it){

            $name = $it['name'] ?? $it['display_name'] ?? 'Unknown';
            $qty = intval($it['qty'] ?? 1);

            $bookingItems[$name] =
                ($bookingItems[$name] ?? 0) + $qty;
        }

    } else {

        $fallback = $b['item_name'] ?? 'Unknown';

        $bookingItems[$fallback] =
            ($bookingItems[$fallback] ?? 0) + 1;
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
    'revenuePerDay' => $revenuePerDay,
    'ordersByHour' => $ordersByHour,
    'ordersByDayOfWeek' => $ordersByDayOfWeek,
    'categorySales' => $categorySales,
    'bookingItems' => $bookingItems,
    'rentItems' => $rentItems,

    'bookingTotalSales' => $bookingTotalSales,
    'bookingTotalOrders' => $bookingTotalOrders,
    'bookingRevenuePerDay' => $bookingRevenuePerDay,
    'bookingOrdersPerDay' => $bookingOrdersPerDay
];