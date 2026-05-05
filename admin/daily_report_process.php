<?php

include(__DIR__ . "/../config.php");
require_once(__DIR__ . "/../firebaseRDB.php");

$rdb = new firebaseRDB($databaseURL);

/* ================= FETCH ================= */

$orders   = json_decode($rdb->retrieve("/orders"), true) ?? [];
$bookings = json_decode($rdb->retrieve("/bookings"), true) ?? [];

/* ================= FILTER ================= */

$view = $_GET['view'] ?? 'daily';
$date = $_GET['date'] ?? date('Y-m-d');

/* ================= MONTH NAVIGATION ================= */

$prevMonth = date('Y-m-d', strtotime($date . ' -1 month'));
$nextMonth = date('Y-m-d', strtotime($date . ' +1 month'));

/* ================= WEEK RANGE ================= */

$timestamp = strtotime($date);
$dayOfWeek = date('N', $timestamp);

$weekStart = date('Y-m-d', strtotime($date . ' -' . ($dayOfWeek - 1) . ' days'));
$weekEnd   = date('Y-m-d', strtotime($weekStart . ' +6 days'));

/* ================= HELPERS ================= */

function parseDateOnly($str){
    if(!$str) return '';
    $t = strtotime($str);
    return $t ? date('Y-m-d', $t) : substr($str,0,10);
}

function peso($num){
    return "₱" . number_format($num,2);
}

/* ================= DATA ================= */

$calendar = [];

$selectedOrders = [];
$selectedBookings = [];

$dailyOrders = 0;
$dailyBookings = 0;
$dailyRevenue = 0;
$dailyBookingRevenue = 0;

/* ================= CHART DATA ================= */

$labels = [];

$orderRevenueData = [];     // 🔵 ORDERS
$bookingRevenueData = [];   // 🔴 BOOKINGS

$orderCountData = [];
$bookingCountData = [];

/* ================= ORDERS ================= */

foreach($orders as $o){

    if(!is_array($o)) continue;

    $d = parseDateOnly($o['created_at'] ?? '');
    if(!$d) continue;

    if(!isset($calendar[$d])){
        $calendar[$d] = [
            'orders'=>0,
            'bookings'=>0,
            'orderRevenue'=>0,
            'bookingRevenue'=>0
        ];
    }

    $calendar[$d]['orders']++;
    $calendar[$d]['orderRevenue'] += floatval($o['total'] ?? 0);

    if($d == $date){
        $dailyOrders++;
        $dailyRevenue += floatval($o['total'] ?? 0);
        $selectedOrders[] = $o;
    }
}

/* ================= BOOKINGS (ONLY ACCEPTED) ================= */

foreach($bookings as $b){

    if(!is_array($b)) continue;

    $status = strtolower($b['status'] ?? '');

    if($status !== 'accepted') continue;

    $d = parseDateOnly($b['created_at'] ?? '');
    if(!$d) continue;

    if(!isset($calendar[$d])){
        $calendar[$d] = [
            'orders'=>0,
            'bookings'=>0,
            'orderRevenue'=>0,
            'bookingRevenue'=>0
        ];
    }

    $calendar[$d]['bookings']++;

    $amount = floatval($b['booking_total'] ?? 0);
    $calendar[$d]['bookingRevenue'] += $amount;

    if($d == $date){
        $dailyBookings++;
        $dailyBookingRevenue += $amount;
        $selectedBookings[] = $b;
    }
}

/* ================= BUILD CHART ================= */

ksort($calendar);

foreach($calendar as $d => $v){

    $labels[] = $d;

    // 🔵 Orders revenue
    $orderRevenueData[] = $v['orderRevenue'];

    // 🔴 Booking revenue
    $bookingRevenueData[] = $v['bookingRevenue'];

    // counts
    $orderCountData[] = $v['orders'];
    $bookingCountData[] = $v['bookings'];
}

/* ================= MONTHLY REVENUE ================= */

$targetMonth = date('Y-m', strtotime($date));

$monthlyOrderRevenue = 0;
$monthlyBookingRevenue = 0;

foreach($orders as $o){
    if(!is_array($o)) continue;

    $month = date('Y-m', strtotime($o['created_at'] ?? ''));
    if($month == $targetMonth){
        $monthlyOrderRevenue += floatval($o['total'] ?? 0);
    }
}

foreach($bookings as $b){
    if(!is_array($b)) continue;

    $status = strtolower($b['status'] ?? '');
    if($status !== 'accepted') continue;

    $month = date('Y-m', strtotime($b['created_at'] ?? ''));
    if($month == $targetMonth){
        $monthlyBookingRevenue += floatval($b['booking_total'] ?? 0);
    }
}

/* ================= CALENDAR ================= */

$year  = date('Y', strtotime($date));
$month = date('m', strtotime($date));

$firstDay = date('Y-m-01', strtotime($date));
$daysInMonth = date('t', strtotime($firstDay));
$startDay = date('w', strtotime($firstDay));

return [
    "date" => $date,
    "view" => $view,

    "selectedOrders" => $selectedOrders,
    "selectedBookings" => $selectedBookings,

    "dailyOrders" => $dailyOrders,
    "dailyBookings" => $dailyBookings,

    "dailyRevenue" => $dailyRevenue,
    "dailyBookingRevenue" => $dailyBookingRevenue,

    "calendar" => $calendar,

    "labels" => $labels,

    // 🔥 NEW FIXED CHART DATA
    "orderRevenueData" => $orderRevenueData,
    "bookingRevenueData" => $bookingRevenueData,

    "orderCountData" => $orderCountData,
    "bookingCountData" => $bookingCountData,

    "year" => $year,
    "month" => $month,

    "startDay" => $startDay,
    "daysInMonth" => $daysInMonth,

    "prevMonth" => $prevMonth,
    "nextMonth" => $nextMonth,

    "monthlyOrderRevenue" => $monthlyOrderRevenue,
    "monthlyBookingRevenue" => $monthlyBookingRevenue,
];
?>