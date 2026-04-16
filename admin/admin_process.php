<?php
include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

// Firebase connection
$rdb = new firebaseRDB($databaseURL);

/* ================= NEW DATA SOURCES ================= */

// ✅ CASHIER ORDER HISTORY
$orders_raw = $rdb->retrieve("/cashier_orderHistory");
$orders = json_decode($orders_raw, true) ?? [];

// ✅ CASHIER BOOKING HISTORY
$bookings_raw = $rdb->retrieve("/cashier_bookinghistory");
$bookings = json_decode($bookings_raw, true) ?? [];

// ✅ KITCHEN HISTORY
$kitchen_raw = $rdb->retrieve("/kitchen_history");
$kitchenData = json_decode($kitchen_raw, true) ?? [];

/* ================= KPI ================= */
$kpis = [
    'todayRevenue' => 0,
    'totalRevenue' => 0,
    'totalSales' => count($orders),
    'totalUsers' => 0,
    'totalBookings' => count($bookings)
];

/* ================= ORDER HISTORY STATUS ================= */
$ordersStatus = [
    'accepted' => 0,
    'rejected' => 0
];

/* ================= BOOKING HISTORY STATUS ================= */
$bookingsStatus = [
    'accepted' => 0,
    'rejected' => 0
];

/* ================= KITCHEN STATUS ================= */
$kitchenStatus = [
    'preparing' => 0,
    'ready' => 0,
    'done' => 0
];

$today = date('Y-m-d');

/* ================= PROCESS CASHIER ORDERS ================= */
foreach($orders as $order){

    $status = strtolower($order['final_status'] ?? '');

    if($status === 'accepted'){
        $ordersStatus['accepted']++;
    } else {
        $ordersStatus['rejected']++;
    }

    $total = floatval($order['total'] ?? 0);
    $kpis['totalRevenue'] += $total;

    if(substr($order['cashier_action_time'] ?? '', 0, 10) === $today){
        $kpis['todayRevenue'] += $total;
    }
}

/* ================= PROCESS CASHIER BOOKINGS ================= */
foreach($bookings as $booking){

    $status = strtolower($booking['final_status'] ?? '');

    if($status === 'accepted'){
        $bookingsStatus['accepted']++;
    } else {
        $bookingsStatus['rejected']++;
    }
}

/* ================= PROCESS KITCHEN HISTORY ================= */
foreach($kitchenData as $k){

    $status = strtolower($k['kitchen_status'] ?? 'preparing');

    if(!isset($kitchenStatus[$status])){
        $status = 'preparing';
    }

    $kitchenStatus[$status]++;
}

/* ================= USERS ================= */
$users_raw = $rdb->retrieve("/user");
$users = json_decode($users_raw, true) ?? [];
$kpis['totalUsers'] = count($users);

/* ================= RETURN ================= */
return [
    'orders' => $orders,
    'bookings' => $bookings,
    'kpis' => $kpis,
    'ordersStatus' => $ordersStatus,        // now = cashier order history
    'bookingsStatus' => $bookingsStatus,    // now = cashier booking history
    'kitchenStatus' => $kitchenStatus
];
?>