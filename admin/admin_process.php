<?php
include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

$rdb = new firebaseRDB($databaseURL);

/* ================= FETCH DATA ================= */

$orders_raw = $rdb->retrieve("/orders");
$orders = json_decode($orders_raw, true) ?? [];

$kitchen_raw = $rdb->retrieve("/kitchen_history");
$kitchenData = json_decode($kitchen_raw, true) ?? [];

$bookings_raw = $rdb->retrieve("/cashier_bookinghistory");
$bookings = json_decode($bookings_raw, true) ?? [];

/* ================= KPI ================= */

$kpis = [
    'todayRevenue' => 0,
    'totalRevenue' => 0,
    'totalSales' => 0,
    'totalUsers' => 0,
    'totalBookings' => count($bookings)
];

/* ================= STATUS COUNTS ================= */

$ordersStatus = [
    'pending' => 0,
    'accepted' => 0,
    'rejected' => 0,
    'done' => 0
];

$kitchenStatus = [
    'preparing' => 0,
    'ready' => 0,
    'done' => 0
];

$bookingsStatus = [
    'pending' => 0,
    'accepted' => 0,
    'rejected' => 0
];

/* ================= NEW: BOOKINGS PER DAY ================= */
$bookingsPerDay = [];

/* ================= BEST SELLING ================= */
$productSales = [];

$today = date('Y-m-d');

/* ================= PROCESS ORDERS ================= */

foreach($orders as $order){

    if(!is_array($order)) continue;

    $status = strtolower($order['status'] ?? 'pending');

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

    // BEST SELLING
    foreach(($order['products'] ?? []) as $p){
        $name = $p['name'] ?? 'Unknown';
        $qty = intval($p['qty'] ?? 0);

        if(!isset($productSales[$name])){
            $productSales[$name] = 0;
        }

        $productSales[$name] += $qty;
    }

    // KITCHEN STATUS
    $kStatus = strtolower($order['kitchen_status'] ?? '');

    if(isset($kitchenStatus[$kStatus])){
        $kitchenStatus[$kStatus]++;
    }
}

/* ================= PROCESS KITCHEN HISTORY ================= */

foreach($kitchenData as $k){

    if(!is_array($k)) continue;

    $kitchenStatus['done']++;

    $kpis['totalRevenue'] += floatval($k['total'] ?? 0);
}

/* ================= PROCESS BOOKINGS ================= */

foreach($bookings as $b){

    if(!is_array($b)) continue;

    $status = strtolower($b['final_status'] ?? 'pending');

    if(isset($bookingsStatus[$status])){
        $bookingsStatus[$status]++;
    } else {
        $bookingsStatus['pending']++;
    }

    /* ================= BOOKINGS PER DAY FIX ================= */
    $date = $b['cashier_action_time']
        ?? $b['created_at']
        ?? '';

    $dateKey = substr($date, 0, 10);

    if($dateKey){
        if(!isset($bookingsPerDay[$dateKey])){
            $bookingsPerDay[$dateKey] = 0;
        }
        $bookingsPerDay[$dateKey]++;
    }
}

/* ================= USERS ================= */

$users_raw = $rdb->retrieve("/user");
$users = json_decode($users_raw, true) ?? [];
$kpis['totalUsers'] = count($users);

/* ================= SORT ================= */

arsort($productSales);
ksort($bookingsPerDay);

/* ================= RETURN ================= */

return [
    'kpis' => $kpis,
    'ordersStatus' => $ordersStatus,
    'bookingsStatus' => $bookingsStatus,
    'kitchenStatus' => $kitchenStatus,
    'bestSelling' => $productSales,
    'bookingsPerDay' => $bookingsPerDay
];
?>