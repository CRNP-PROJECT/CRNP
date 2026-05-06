<?php

date_default_timezone_set('Asia/Manila');

$rdb = new firebaseRDB($databaseURL);

/* ================= FETCH DATA ================= */

$orders = json_decode($rdb->retrieve("/orders"), true) ?? [];
$bookings = json_decode($rdb->retrieve("/bookings"), true) ?? [];

/* ================= DATE ================= */

$today = date('Y-m-d');
$current_month = date('Y-m');

/* ================= ORDERS ================= */

$today_order_sales = 0;
$monthly_order_sales = 0;
$order_count = 0;
$pending_orders = [];

foreach($orders as $id => $order){

    if(empty($order['created_at'])) continue;

    $created = strtotime($order['created_at']);
    if(!$created) continue;

    $order_date = date('Y-m-d', $created);
    $order_month = date('Y-m', $created);

    $status = strtolower($order['status'] ?? '');
    $payment_status = strtolower($order['payment_status'] ?? '');
    $total = floatval($order['total'] ?? 0);

    $is_paid =
        $payment_status === 'paid' ||
        $payment_status === 'no_payment_required';

    if($is_paid){

        if($order_date === $today){
            $today_order_sales += $total;
            $order_count++;
        }

        if($order_month === $current_month){
            $monthly_order_sales += $total;
        }
    }

    if($status === 'pending'){
        $order['id'] = $id;
        $pending_orders[] = $order;
    }
}

$today_booking_sales = 0;
$monthly_booking_sales = 0;
$booking_count = 0;
$today_bookings = [];

foreach($bookings as $id => $b){

    if(!is_array($b)) continue;

    // ✅ DATE (ONLY ONE SOURCE)
    $time_source = $b['created_at'] ?? null;
    if(!$time_source) continue;

    $time = strtotime($time_source);
    if(!$time) continue;

    $booking_date = date('Y-m-d', $time);
    $booking_month = date('Y-m', $time);

    // ✅ AMOUNT (ONLY ONE SOURCE)
    $total = floatval($b['booking_total'] ?? 0);

    // ✅ STATUS RULE (UNIFIED)
    $status = strtolower($b['status'] ?? '');
    $payment_status = strtolower($b['payment_status'] ?? '');

    $is_paid =
        $status === 'accepted' ||
        $status === 'confirmed' ||
        $payment_status === 'paid' ||
        $payment_status === 'counter';

    // ================= TODAY SALES =================
    if($is_paid && $booking_date === $today){
        $today_booking_sales += $total;
        $booking_count++;
    }

    // ================= MONTHLY SALES =================
    if($is_paid && $booking_month === $current_month){
        $monthly_booking_sales += $total;
    }

    // ================= TODAY BOOKINGS LIST =================
    if($booking_date === $today){
        $b['id'] = $id;
        $today_bookings[] = $b;
    }
}

/* ================= AVG ORDER ================= */

$avg_order = ($order_count > 0)
    ? ($today_order_sales / $order_count)
    : 0;

/* ================= SORT BOOKINGS ================= */

usort($today_bookings, function($a, $b){
    return strtotime($a['appointment_time'] ?? '')
        <=> strtotime($b['appointment_time'] ?? '');
});



function getScheduledBookings($rdb){

    $bookings = json_decode($rdb->retrieve("/bookings"), true) ?? [];

    $scheduled = [];

    foreach($bookings as $id => $b){

        if(!is_array($b)) continue;

        if(($b['status'] ?? '') !== 'accepted') continue;

        $datetime = $b['appointment_time'] ?? '';

        if(!$datetime) continue;

        /* ================= FORMAT DATE/TIME ================= */
        $date = date("Y-m-d", strtotime($datetime));
        $time = date("h:i A", strtotime($datetime));

        /* ================= ITEMS PROCESS ================= */
        $itemsText = "";
        $itemsArray = $b['items'] ?? [];

        if(is_array($itemsArray) && count($itemsArray) > 0){

            foreach($itemsArray as $item){
                $name  = $item['name'] ?? 'Item';
                $qty   = $item['qty'] ?? 1;
                $price = $item['price'] ?? 0;

                $itemsText .= "• {$name} (x{$qty}) - ₱{$price}<br>";
            }
        }

        /* ================= PAYMENT LOGIC ================= */
        $paymentMethod = $b['payment_method'] ?? '';
        $paymentStatusRaw = $b['payment_status'] ?? '';

        if($paymentMethod === "counter"){
            $paymentStatus = "PAID (Counter)";
            $paymentColor = "text-success";
        }
        else if($paymentStatusRaw === "no_payment_required"){
            $paymentStatus = "No Payment Required";
            $paymentColor = "text-warning";
        }
        else{
            $paymentStatus = ucfirst($paymentStatusRaw ?: "Pending");
            $paymentColor = "text-danger";
        }

        /* ================= FINAL STRUCTURE ================= */
        $scheduled[] = [
            "id" => $id,
            "name" => $b['full_name'] ?? 'Unknown',
            "address" => $b['address'] ?? '',
            "contact" => $b['contact_number'] ?? '',
            "email" => $b['user_email'] ?? '',
            "total" => $b['booking_total'] ?? 0,
            "payment_method" => $paymentMethod,
            "payment_status" => $paymentStatus,
            "payment_color" => $paymentColor,
            "items" => $itemsText,
            "date" => $date,
            "time" => $time,
            "created_at" => $b['created_at'] ?? '',
            "updated_at" => $b['updated_at'] ?? ''
        ];
    }

    return $scheduled;
}


$latest_pending_bookings = [];

foreach($bookings as $id => $b){

    if(!is_array($b)) continue;

    if(($b['status'] ?? '') !== 'pending') continue;

    $latest_pending_bookings[] = [
        "id" => $id,
        "full_name" => $b['full_name'] ?? '',
        "appointment_time" => $b['appointment_time'] ?? ''
    ];
}

/* sort latest first */
usort($latest_pending_bookings, function($a, $b){
    return strtotime($b['appointment_time']) <=> strtotime($a['appointment_time']);
});


/* ================= RETURN DATA ================= */

return [
    'today_order_sales' => $today_order_sales,
    'today_booking_sales' => $today_booking_sales,
    'today_total_sales' => $today_order_sales + $today_booking_sales,
    'avg_order' => $avg_order,

    'pending_orders' => $pending_orders,
    'today_bookings' => $today_bookings,

    'order_count' => $order_count,
    'booking_count' => $booking_count,
];