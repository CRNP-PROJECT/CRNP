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

    // ================= DATE =================
    $time_source = $b['created_at'] ?? null;
    if(!$time_source) continue;

    $time = strtotime($time_source);
    if(!$time) continue;

    $booking_date = date('Y-m-d', $time);
    $booking_month = date('Y-m', $time);

    // ================= AMOUNT =================
    $total = floatval($b['booking_total'] ?? 0);

    // ================= STATUS =================
    $status = strtolower($b['status'] ?? '');
    $payment_status = strtolower($b['payment_status'] ?? '');

    /**
     * ✅ FIXED RULE:
     * Booking is valid for sales if:
     * - status is accepted / confirmed / done / returned
     * - OR payment is already completed
     */
    $is_valid_booking =
        in_array($status, ['accepted', 'confirmed', 'done', 'returned']) ||
        in_array($payment_status, ['paid', 'counter', 'no_payment_required']);

    // ================= TODAY SALES =================
    if($is_valid_booking && $booking_date === $today){
        $today_booking_sales += $total;
        $booking_count++;
    }

    // ================= MONTHLY SALES =================
    if($is_valid_booking && $booking_month === $current_month){
        $monthly_booking_sales += $total;
    }

    // ================= TODAY LIST =================
    if($booking_date === $today){
        $b['id'] = $id;
        $today_bookings[] = $b;
    }
}


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

$booking_returns = [];

foreach($bookings as $id => $b){

    if(!is_array($b)) continue;

    $status = strtolower($b['status'] ?? '');

    // ONLY ACCEPTED / DONE / RETURNED
    if(!in_array($status, ['accepted','done','returned'])){
        continue;
    }

    $returnRaw = $b['return_time'] ?? '';
    if(empty($returnRaw)) continue;

    $returnDate = date('Y-m-d', strtotime($returnRaw));
    $returnTime = date('h:i A', strtotime($returnRaw));

    /* ================= FILTER BY DATE ================= */
    $selected_date = $_GET['date'] ?? date("Y-m-d");

    if($returnDate != $selected_date){
        continue;
    }

    /* ================= FILTER TYPE ================= */
    $filter = $_GET['filter'] ?? 'all';

    if($filter == 'pending' && $status == 'returned'){
        continue;
    }

    if($filter == 'returned' && $status != 'returned'){
        continue;
    }

    /* ================= DUE SOON LOGIC ================= */
    $daysLeft = (strtotime($returnRaw) - time()) / 86400;

    $dueSoon = ($daysLeft <= 5 && $daysLeft >= 0);

    /* ================= FINAL DATA ================= */
    $booking_returns[] = [
        "id" => $id,
        "name" => $b['full_name'] ?? 'Unknown',
        "contact" => $b['contact_number'] ?? '',
        "address" => $b['address'] ?? '',
        "total" => $b['booking_total'] ?? 0,

        "return_time" => date("M d, Y h:i A", strtotime($returnRaw)),

        "status" => $status,

        "delivery_note" => $b['delivery_note'] ?? '',
        "returned_at" => $b['returned_at'] ?? '',

        // 🔥 IMPORTANT: FOR CALENDAR INDICATOR
        "due_soon" => $dueSoon
    ];

}

/* ================= RETURN DATA ================= */

return [
    'today_order_sales' => $today_order_sales,
    'today_booking_sales' => $today_booking_sales,
    'today_total_sales' => $today_order_sales + $today_booking_sales,
    

    'pending_orders' => $pending_orders,
    'today_bookings' => $today_bookings,

    'order_count' => $order_count,
    'booking_count' => $booking_count,
];