<?php
include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

// Firebase connection
$rdb = new firebaseRDB($databaseURL);

// ----- FETCH ORDERS -----
$orders_raw = $rdb->retrieve("/orders");
$orders = json_decode($orders_raw, true) ?? [];

// ----- FETCH BOOKINGS -----
$bookings_raw = $rdb->retrieve("/bookings");
$bookings = json_decode($bookings_raw, true) ?? [];

// ----- KPI COUNTERS -----
$kpis = [
    'todayRevenue' => 0,
    'totalRevenue' => 0,
    'totalSales' => 0,
    'totalUsers' => 0,
    'totalBookings' => count($bookings)
];

// Orders Status
$ordersStatus = [
    'pending' => 0,
    'accepted' => 0,
    'rejected' => 0
];

// Kitchen Status
$kitchenStatus = [
    'preparing' => 0,
    'ready' => 0,
    'done' => 0
];

// Bookings Status
$bookingsStatus = [
    "pending" => 0,
    "accepted" => 0,
    "rejected" => 0
];

// Today's date
$today = date('Y-m-d');

// ----- CALCULATE ORDER KPI AND STATUS -----
foreach($orders as $order){
    $status = $order['status'] ?? 'pending';
    if(!isset($ordersStatus[$status])) $status = 'pending';
    $ordersStatus[$status]++;

    $total = floatval($order['total'] ?? 0);
    $kpis['totalRevenue'] += $total;

    if(substr($order['created_at'] ?? '',0,10) === $today){
        $kpis['todayRevenue'] += $total;
    }

    $kpis['totalSales']++;

    // Kitchen status
    $kitchen = $order['kitchen_status'] ?? 'preparing';
    if(!isset($kitchenStatus[$kitchen])) $kitchen = 'preparing';
    $kitchenStatus[$kitchen]++;
}

// ----- TOTAL USERS -----
$users_raw = $rdb->retrieve("/user");
$users = json_decode($users_raw, true) ?? [];
$kpis['totalUsers'] = count($users);

// ----- BOOKINGS PER DAY -----
$bookingsPerDay = [];
foreach($bookings as $b){
    $date = substr($b['created_at'] ?? '',0,10);
    if(!isset($bookingsPerDay[$date])){
        $bookingsPerDay[$date] = 0;
    }
    $bookingsPerDay[$date]++;
}

// ----- BEST SELLING PRODUCTS -----
$bestSelling = [];
foreach($orders as $order){
    if(isset($order['products']) && is_array($order['products'])){
        foreach($order['products'] as $p){
            $name = $p['name'] ?? 'Unknown';
            $qty = intval($p['qty'] ?? 0);

            if(!isset($bestSelling[$name])) $bestSelling[$name] = 0;
            $bestSelling[$name] += $qty;
        }
    }
}

// ----- BOOKINGS STATUS COUNT -----
if(!empty($bookings)){
    foreach($bookings as $id => $booking){
        $status = strtolower($booking['status'] ?? 'pending');

        if($status === "pending"){
            $bookingsStatus['pending']++;
        } elseif($status === "accepted"){
            $bookingsStatus['accepted']++;
        } elseif($status === "rejected"){
            $bookingsStatus['rejected']++;
        }
    }
}

// ----- BOOKING ITEMS PER DAY -----
$bookingItemsPerDay = [];
foreach($bookings as $b){
    $date = substr($b['created_at'] ?? '',0,10);
    if(!isset($bookingItemsPerDay[$date])) $bookingItemsPerDay[$date] = 0;

    $tables = intval($b['tables_qty'] ?? 0);
    $chairs = intval($b['chairs_qty'] ?? 0);

    $skirtingTotal = 0;
    if(isset($b['skirting']) && is_array($b['skirting'])){
        foreach($b['skirting'] as $s){
            $skirtingTotal += intval($s['qty'] ?? 0);
        }
    } else {
        $skirtingTotal = intval($b['skirting_qty'] ?? 0);
    }

    $bookingItemsPerDay[$date] += ($tables + $chairs + $skirtingTotal);
}

// ----- RETURN ALL DATA -----
return [
    'orders' => $orders,
    'bookings' => $bookings,
    'kpis' => $kpis,
    'ordersStatus' => $ordersStatus,
    'kitchenStatus' => $kitchenStatus,
    'bookingsPerDay' => $bookingsPerDay,
    'bestSelling' => $bestSelling,
    'bookingItemsPerDay' => $bookingItemsPerDay,
    'bookingsStatus' => $bookingsStatus
];
?>