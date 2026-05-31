     <?php
    include(__DIR__ . "/../config.php");
    require_once(__DIR__ . "/../firebaseRDB.php");

    $rdb = new firebaseRDB($databaseURL);

    /* ================= FETCH DATA ================= */

    $orders = json_decode($rdb->retrieve("/orders"), true) ?? [];
    $kitchenData = json_decode($rdb->retrieve("/kitchen_history"), true) ?? [];
    $bookings = json_decode($rdb->retrieve("/bookings"), true) ?? [];
    $users = json_decode($rdb->retrieve("/user"), true) ?? [];
    $rentItems = json_decode($rdb->retrieve("/rent_items"), true) ?? [];

    /* ================= DATE FILTER ================= */
    $selectedDate = $_GET['date'] ?? null;



    /* ================= KPI ================= */

    $kpis = [
        'todayRevenue' => 0,
        'totalRevenue' => 0,
        'totalSales' => 0,

        'todaySales' => 0,
        'todayOrders' => 0,

        'selectedRevenue' => 0,
        'selectedSales' => 0,

        // ✅ FIX ONLY (was missing, used later in charts)
        'bookingRevenue' => 0,

        'totalUsers' => count($users),

        
    ];

    /* ================= BOOKING EXTRA KPI ================= */

    $bookingTotalSales = 0;
    $bookingTotalOrders = 0;

    $bookingRevenuePerDay = [];
    $bookingOrdersPerDay = [];

    $todayBookingSales = 0;
    $todayBookingOrders = 0;

    /* ================= STATUS ================= */

    $ordersStatus = ['pending'=>0,'accepted'=>0,'rejected'=>0,'done'=>0];
    $kitchenStatus = ['preparing'=>0,'ready'=>0,'done'=>0];
    $bookingsStatus = ['pending'=>0,'accepted'=>0,'done=>0','rejected'=>0];

    $bookingsPerDay = [];
    $productSales = [];

    $bestSellingToday = [];
    $bestSellingPerDay = [];

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

        $createdAt = $order['created_at'] ?? null;
        if(!$createdAt) continue;

        $orderDate = date('Y-m-d', strtotime($createdAt));

        if ($selectedDate && $orderDate !== $selectedDate) {
            continue;
        }

        /* ================= STATUS ================= */
        $status = strtolower(trim($order['status'] ?? 'pending'));
        if(isset($ordersStatus[$status])){
            $ordersStatus[$status]++;
            } else {
                $ordersStatus['pending']++;
                }

/* 🔥 ADD THIS LINE */
if ($status !== 'accepted') continue;

        /* ================= SALES ================= */
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

        /* ================= PRODUCTS ================= */
        foreach($order['products'] ?? [] as $p){

            $name = $p['name'] ?? 'Unknown';
            $qty  = intval($p['qty'] ?? 0);

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

            // ✅ BEST SELLING PER DAY (ADD THIS)
            $bestSellingPerDay[$orderDate][$name] =
                ($bestSellingPerDay[$orderDate][$name] ?? 0) + $qty;

            // ✅ BEST SELLING TODAY (ADD THIS)
            if($orderDate === $today){
                $bestSellingToday[$name] =
                    ($bestSellingToday[$name] ?? 0) + $qty;
            }
        }

        /* ================= REVENUE PER DAY ================= */
        $revenuePerDay[$orderDate] =
            ($revenuePerDay[$orderDate] ?? 0) + $total;

        /* ================= HOURLY ================= */
        $hour = date("g A", strtotime($createdAt)); // e.g. 1 PM, 2 AM
        $ordersByHour[$hour] =
            ($ordersByHour[$hour] ?? 0) + 1;

        /* ================= DAY OF WEEK ================= */
        $dow = date("D", strtotime($createdAt));
        $ordersByDayOfWeek[$dow] =
            ($ordersByDayOfWeek[$dow] ?? 0) + 1;
    }

    /* ================= BEST SELLING (LATEST DAY - ALL PRODUCTS) ================= */

    $bestSellingPerDayLabels = [];
    $bestSellingPerDayData = [];
    $bestSellingPerDayNames = [];

    if (!empty($bestSellingPerDay)) {

        // Get latest date
        ksort($bestSellingPerDay);
        $latestDate = array_key_last($bestSellingPerDay);

        $items = $bestSellingPerDay[$latestDate] ?? [];

        if (is_array($items) && !empty($items)) {

            arsort($items); // highest to lowest

            foreach ($items as $productName => $qty) {

                $bestSellingPerDayNames[]  = $productName;
                $bestSellingPerDayData[]   = $qty;
                $bestSellingPerDayLabels[] = $productName; // chart labels = products
            }
        }
    }

    $categorySalesLatestDay = [
        "Foods" => 0,
        "Drinks" => 0,
        "Beverages" => 0
    ];

    /* STEP 1: find latest ORDER date (NOT revenue array) */
   $latestDate = null;

foreach ($orders as $order) {

    if (!is_array($order)) continue;

    /* ✅ ADD THIS FILTER */
    $status = strtolower(trim($order['status'] ?? ''));
    if ($status !== 'accepted') continue;

    $createdAt = $order['created_at'] ?? null;
    if (!$createdAt) continue;

    $date = date('Y-m-d', strtotime($createdAt));

    if ($latestDate === null || $date > $latestDate) {
        $latestDate = $date;
    }
}

    /* STEP 2: build category sales ONLY for latest date */
if ($latestDate) {

    foreach ($orders as $order) {

        if (!is_array($order)) continue;

        /* ✅ ADD THIS */
        $status = strtolower(trim($order['status'] ?? ''));
        if ($status !== 'accepted') continue;

        $createdAt = $order['created_at'] ?? null;
        if (!$createdAt) continue;

        $orderDate = date('Y-m-d', strtotime($createdAt));

        if ($orderDate !== $latestDate) continue;

        foreach ($order['products'] ?? [] as $p) {

            $qty = intval($p['qty'] ?? 0);
            if ($qty <= 0) continue;

            $productId = $p['product_id'] ?? null;

            $cat = '';

            if ($productId && isset($productMap[$productId]['category'])) {
                $cat = strtolower($productMap[$productId]['category']);
            } else {
                $cat = strtolower($p['category'] ?? '');
            }

            if (strpos($cat, 'food') !== false) {
                $categorySalesLatestDay['Foods'] += $qty;
            }
            elseif (strpos($cat, 'drink') !== false) {
                $categorySalesLatestDay['Drinks'] += $qty;
            }
            elseif (strpos($cat, 'beverage') !== false) {
                $categorySalesLatestDay['Beverages'] += $qty;
            }
        }
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
    /* ================= BOOKING REVENUE (LATEST DAY ONLY) ================= */

    ksort($bookingRevenuePerDay);
$latestBookingDate = array_key_last($bookingRevenuePerDay);

$bookingRevenueLatestDay = $bookingRevenuePerDay[$latestBookingDate] ?? 0;
    
    /* ================= BOOKINGS (FIXED: ONLY ACCEPTED SALES) ================= */

    /* ensure safety */
    $kpis['bookingRevenue'] = $kpis['bookingRevenue'] ?? 0;
    $bookingRevenuePerDay = $bookingRevenuePerDay ?? [];
    $bookingOrdersPerDay = $bookingOrdersPerDay ?? [];
    $bookingsPerDay = $bookingsPerDay ?? [];

    $latestBookingDate = null;

foreach ($bookings as $b) {

    if (!is_array($b)) continue;

    $status = strtolower(trim($b['status'] ?? ''));

    // include real completed + accepted bookings
    if (!in_array($status, ['accepted', 'done'])) {
        continue;
    }

    $created = $b['created_at'] ?? null;
    if (!$created) continue;

    $createdDate = date('Y-m-d', strtotime($created));

    if ($latestBookingDate === null || $createdDate > $latestBookingDate) {
        $latestBookingDate = $createdDate;
    }
}
    /* ================= RESET LOOP FOR PROCESSING ================= */

    $bookingRevenueLatestDay = 0;

    foreach ($bookings as $b) {

        if (!is_array($b)) continue;

        $status = strtolower(trim($b['status'] ?? ''));

        /* status chart */
        if (isset($bookingsStatus[$status])) {
            $bookingsStatus[$status]++;
        } else {
            $bookingsStatus['pending']++;
        }

        /* ONLY accepted bookings */
        if (!in_array($status, ['accepted', 'done'])) continue;

        $bookingTotal = floatval($b['booking_total'] ?? 0);

        /* ================= IMPORTANT FIX ================= */
        $created = $b['created_at'] ?? null;
        if (!$created) continue;

        $createdDate = date('Y-m-d', strtotime($created));

        /* appointment ONLY for optional schedule use */
        $appointmentDate = $b['appointment_time'] ?? null;
        if ($appointmentDate) {
            $appointmentDate = date('Y-m-d', strtotime($appointmentDate));
        }

        /* ================= TOTAL SALES ================= */
        $bookingTotalSales += $bookingTotal;
        $bookingTotalOrders++;

        /* ================= TODAY SALES (BASED ON CREATED_AT) ================= */
        if ($createdDate === $today) {

            $todayBookingSales += $bookingTotal;
            $todayBookingOrders++;

            $kpis['bookingRevenue'] += $bookingTotal;
            $kpis['todayOrders']++;
        }

        /* ================= AOV ================= */
        $todayBookingAOV = ($todayBookingOrders > 0)
            ? ($todayBookingSales / $todayBookingOrders)
            : 0;

        /* ================= DAILY REVENUE ================= */
        $bookingRevenuePerDay[$createdDate] =
            ($bookingRevenuePerDay[$createdDate] ?? 0) + $bookingTotal;

        $bookingOrdersPerDay[$createdDate] =
            ($bookingOrdersPerDay[$createdDate] ?? 0) + 1;

        $bookingsPerDay[$createdDate] =
            ($bookingsPerDay[$createdDate] ?? 0) + 1;

        /* ================= LATEST DAY REVENUE ================= */
        if ($createdDate === $latestBookingDate) {
            $bookingRevenueLatestDay += $bookingTotal;
        }

        /* ================= ITEMS ================= */
        if (!empty($b['items'])) {

            foreach ($b['items'] as $it) {

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
   $latestBookings = [
    "labels" => [],
    "data"   => []
];

$hourly = array_fill(0, 24, 0);
$today  = date("Y-m-d");

foreach ($bookings as $b) {

    if (!is_array($b)) continue;

   $status = strtolower(trim($b['status'] ?? ''));

if (!in_array($status, ['accepted', 'done'])) {
    continue;
}

    // ✅ FIXED: unified field
    $time = $b['created_at'] ?? null;
    if (!$time) continue;

    $date = date("Y-m-d", strtotime($time));
    if ($date !== $today) continue;

    $hour = (int) date("H", strtotime($time));
    $hourly[$hour]++;
}

$latestBookings["labels"] = array_map(function($h){
    return date("g:00 A", strtotime("$h:00"));
}, range(0, 23));

$latestBookings["data"] = array_values($hourly);


    /* ================= TODAY MOST BOOKED ================= */

   $todayMostBooked = [];

foreach ($bookings as $b) {

    if (!is_array($b)) continue;

    $status = strtolower(trim($b['status'] ?? ''));

    // ✅ ONLY valid bookings
    if (!in_array($status, ['accepted', 'done', 'completed', 'finished'])) {
        continue;
    }

    $time = $b['created_at'] ?? null;
    if (!$time) continue;

    $date = date("Y-m-d", strtotime($time));
    if ($date !== $today) continue;

    $items = $b['items'] ?? null;
    if (!$items) continue;

    if (is_array($items)) {

        foreach ($items as $i) {

            $name = is_array($i) ? ($i['name'] ?? 'Item') : $i;
            $qty  = is_array($i) ? (int)($i['qty'] ?? 1) : 1;

            if (!$name) continue;

            $todayMostBooked[$name] =
                ($todayMostBooked[$name] ?? 0) + $qty;
        }

    } else {

        $todayMostBooked[$items] =
            ($todayMostBooked[$items] ?? 0) + 1;
    }
}
    /* ================= RETURN ================= */

    return [
        'kpis' => $kpis,

        /* ================= STATUS ================= */
        'ordersStatus' => $ordersStatus,
        'bookingsStatus' => $bookingsStatus,
        'kitchenStatus' => $kitchenStatus,

        /* ================= SALES ================= */
        'bestSelling' => $productSales,
        'bestSellingToday' => $bestSellingToday,
        'bestSellingPerDay' => $bestSellingPerDay,

        /* ================= ANALYTICS ================= */
        'bookingsPerDay' => $bookingsPerDay,
        'revenuePerDay' => $revenuePerDay,
        'ordersByHour' => $ordersByHour,
        'ordersByDayOfWeek' => $ordersByDayOfWeek,

        /* ================= CATEGORY ================= */
        'categorySales' => $categorySales,
        'categorySalesLatestDay' => $categorySalesLatestDay,

        /* ================= BOOKING ================= */
        'bookingItems' => $bookingItems,
        'rentItems' => $rentItems,

        'bookingTotalSales' => $bookingTotalSales,
        'bookingTotalOrders' => $bookingTotalOrders,
        'bookingRevenuePerDay' => $bookingRevenuePerDay,
        'bookingOrdersPerDay' => $bookingOrdersPerDay,

        // 🔥 YOUR EXISTING
        'bookingRevenueLatestDay' => $bookingRevenueLatestDay,
        'latestBookingDate' => $latestBookingDate,

        // ✅ ADD THIS LINE
        'latestBookings' => $latestBookings,
    ];