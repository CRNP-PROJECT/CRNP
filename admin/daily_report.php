<?php
session_start();

include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

$rdb = new firebaseRDB($databaseURL);

/* ================= FETCH ================= */

$orders   = json_decode($rdb->retrieve("/orders"), true) ?? [];
$bookings = json_decode($rdb->retrieve("/bookings"), true) ?? [];

/* ================= FILTER ================= */

$view = $_GET['view'] ?? 'daily';
$date = $_GET['date'] ?? date('Y-m-d');

/* ================= MONTH NAVIGATION (ADDED) ================= */

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
$orderRevenueData = [];    /* CHANGED: Split from unified array */
$bookingRevenueData = [];  /* CHANGED: Split from unified array */
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
    $orderRevenueData[] = $v['orderRevenue'];      /* CHANGED: Isolating order amounts */
    $bookingRevenueData[] = $v['bookingRevenue'];  /* CHANGED: Isolating booking amounts */
    $orderCountData[] = $v['orders'];
    $bookingCountData[] = $v['bookings'];
}

/* ================= CALENDAR ================= */

$year  = date('Y', strtotime($date));
$month = date('m', strtotime($date));

$firstDay = date('Y-m-01', strtotime($date));
$daysInMonth = date('t', strtotime($firstDay));
$startDay = date('w', strtotime($firstDay));
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>POS Analytics Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</head>

<body class="daily_report_body">
    <aside class="sidebar-navigation-aside" id="sidebar">
        <div class="sidebar-header-brand-container">
            <div class="brand-logo-wrapper">
                <span class="logo-mini-text">CNP</span>
                <span class="logo-full-text">Crates N' Plates</span>
            </div>
        </div>

        <ul class="sidebar-menu-list">
            <li>
                <a href="admin_index.php" class="active">
                    <i class="fa-solid fa-chart-pie nav-vector-icon"></i>
                    <span class="nav-item-label-text">Admin Dashboard</span> 
                </a>
            </li>
            <li>
                <a href="create_cashier.php">
                    <i class="fa-solid fa-cash-register nav-vector-icon"></i>
                    <span class="nav-item-label-text">Cashier Portal</span>
                </a>
            </li>
            <li>
                <a href="create_kitchen.php">
                    <i class="fa-solid fa-utensils nav-vector-icon"></i>
                    <span class="nav-item-label-text">Kitchen Display</span>
                </a>
            </li>

            <li class="sidebar-menu-dropdown-item">
                <button type="button" class="sidebar-submenu-trigger-btn">
                    <span class="submenu-trigger-left-block">
                        <i class="fa-solid fa-boxes-stacked nav-vector-icon"></i>
                        <span class="nav-item-label-text">Products Inventory</span>
                    </span>
                    <i class="fa-solid fa-chevron-down dropdown-arrow-indicator"></i>
                </button>
                <div class="nested-submenu-wrapper">
                    <a href="add_product.php">Add Product</a>
                    <a href="product_list.php">Product List</a>
                </div>
            </li>

            <li class="sidebar-menu-dropdown-item">
                <button type="button" class="sidebar-submenu-trigger-btn">
                    <span class="submenu-trigger-left-block">
                        <i class="fa-solid fa-calendar-check nav-vector-icon"></i>
                        <span class="nav-item-label-text">Reservations</span>
                    </span>
                    <i class="fa-solid fa-chevron-down dropdown-arrow-indicator"></i>
                </button>
                <div class="nested-submenu-wrapper">
                    <a href="booking_add.php">Add Booking Items</a>
                    <a href="booking_list.php">Booking List</a>
                    <a href="booking_reserve.php">Booking Reserve</a>
                </div>
            </li>

            <li>
                <a href="daily_report.php">
                    <i class="fa-solid fa-receipt nav-vector-icon"></i>
                    <span class="nav-item-label-text">Daily Report</span>
                </a>
            </li>
            
            <li class="sidebar-logout-container">
                <a href="admin_log.php">
                    <i class="fa-solid fa-right-from-bracket nav-vector-icon"></i>
                    <span class="nav-item-label-text">Logout</span>
                </a>
            </li>
        </ul>
    </aside>

<div class="daily_report_container">
    <button class="sidebar-brand-toggle-btn" id="sidebarToggle" type="button">
                    <i class="fa-solid fa-bars"></i>
                </button>

    <div class="daily_report_title_section">

        <h1 class="daily_report_title">
            POS Analytics Dashboard
        </h1>

        <p class="daily_report_subtitle">
            Daily • Weekly • Monthly Reports
        </p>

    </div>

    <div class="daily_report_button_group">

        <a
        href="?view=daily&date=<?= $date ?>"
        class="daily_report_btn <?= $view == 'daily' ? 'daily_report_btn_active' : '' ?>">

            Daily

        </a>

        <a
        href="?view=weekly&date=<?= $date ?>"
        class="daily_report_btn <?= $view == 'weekly' ? 'daily_report_btn_active' : '' ?>">

            Weekly

        </a>

        <a
        href="?view=monthly&date=<?= $date ?>"
        class="daily_report_btn <?= $view == 'monthly' ? 'daily_report_btn_active' : '' ?>">

            Monthly

        </a>

    </div>

    <div class="daily_report_month_nav">

        <a
        class="daily_report_btn"
        href="?view=monthly&date=<?= $prevMonth ?>">

            Prev Month

        </a>

        <input
        type="month"
        value="<?= date('Y-m', strtotime($date)) ?>"
        onchange="location='?view=monthly&date='+this.value+'-01'">

        <a
        class="daily_report_btn"
        href="?view=monthly&date=<?= $nextMonth ?>">

            Next Month

        </a>

    </div>

    <div class="daily_report_kpi_grid">

        <div class="daily_report_kpi_card">

            <h6>Orders</h6>

            <h4>
                <?= $dailyOrders ?>
            </h4>

        </div>

        <div class="daily_report_kpi_card">

            <h6>Revenue</h6>

            <h4>
                <?= peso($dailyRevenue) ?>
            </h4>

        </div>

        <div class="daily_report_kpi_card">

            <h6>Bookings</h6>

            <h4>
                <?= $dailyBookings ?>
            </h4>

        </div>

        <div class="daily_report_kpi_card">

            <h6>Booking Revenue</h6>

            <h4>
                <?= peso($dailyBookingRevenue) ?>
            </h4>

        </div>

        <div class="daily_report_kpi_card">
            <h6>No. Bookings</h6>
            <h4><?= $dailyBookings ?></h4>
        </div>

    </div>

    <div class="daily_report_card">

        <h5 class="daily_report_card_title">
            <?= date('F Y', strtotime($date)) ?>
        </h5>

        <div class="daily_report_calendar">

            <?php for($i = 0; $i < 7; $i++): ?>

                <div class="daily_report_calendar_dayname">

                    <?= ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][$i] ?>

                </div>

            <?php endfor; ?>

            <?php for($i = 0; $i < $startDay; $i++): ?>

                <div></div>

            <?php endfor; ?>

            <?php for($d = 1; $d <= $daysInMonth; $d++):

                $full = date('Y-m-d', strtotime("$year-$month-$d"));

                $o  = $calendar[$full]['orders'] ?? 0;
                $b  = $calendar[$full]['bookings'] ?? 0;

                $or = $calendar[$full]['orderRevenue'] ?? 0;
                $br = $calendar[$full]['bookingRevenue'] ?? 0;

                $class = "";

                if($o > 0 && $b > 0){

                    $class = "daily_report_both";

                }elseif($o > 0){

                    $class = "daily_report_orders_only";

                }elseif($b > 0){

                    $class = "daily_report_bookings_only";
                }

            ?>

            <a
            href="?date=<?= $full ?>"
            class="daily_report_day_box <?= $class ?> <?= $full == $date ? 'daily_report_active_day' : '' ?>">

                <div class="daily_report_day_number">

                    <?= $d ?>

                </div>

                <div class="daily_report_muted">

                    Orders: <?= $o ?>

                </div>

                <div class="daily_report_muted">

                    Bookings: <?= $b ?>

                </div>

                <div class="daily_report_muted">

                    <?= peso($or + $br) ?>

                </div>

            </a>

            <?php endfor; ?>

        </div>

    </div>

    <div class="daily_report_chart_grid">

        <div class="daily_report_card">

            <h5 class="daily_report_card_title">
                Revenue Trend
            </h5>

            <canvas id="revenueChart"></canvas>

        </div>

        <div class="daily_report_card">

            <h5 class="daily_report_card_title">
                Orders vs Bookings
            </h5>

            <canvas id="barChart"></canvas>

        </div>

    </div>

    <div class="daily_report_two_column">

        <div class="daily_report_card">

            <h5 class="daily_report_card_title">
                Orders
            </h5>

            <?php foreach($selectedOrders as $o): ?>

            <div class="daily_report_detail_item">

                <b>
                    <?= peso($o['total'] ?? 0) ?>
                </b>

                <br>

                <?php

                $products = $o['products'] ?? [];

                if(is_string($products)){

                    $products = json_decode($products, true);
                }

                if(is_array($products) && !empty($products)){

                    echo "<small><b>Items Ordered:</b><br>";

                    foreach($products as $p){

                        $name = $p['name'] ?? $p['product_name'] ?? 'Item';

                        $qty = $p['qty'] ?? $p['quantity'] ?? 1;

                        echo "• $name x $qty<br>";
                    }

                    echo "</small><br>";
                }

                $payment = $o['payment_method'] ?? 'N/A';

                echo "<small><b>Payment Method:</b> $payment</small><br>";

                ?>

                <small>
                    <?= $o['created_at'] ?? '' ?>
                </small>

            </div>

            <?php endforeach; ?>

        </div>

        <div class="daily_report_card">

            <h5 class="daily_report_card_title">
                All Bookings 
            </h5>

            <?php foreach($selectedBookings as $b): ?>

            <div class="daily_report_detail_item">

                <b>
                    <?= peso($b['booking_total'] ?? 0) ?>
                </b>

                <br>

                <?php

                $item = $b['items'] ?? 'No Item';

                $payment = $b['payment_method'] ?? 'N/A';

                echo "<small><b>Booking Details:</b><br>";

                echo "• Items: ";

                if(is_array($item)){

                    foreach($item as $i){

                        if(is_string($i)){

                            echo $i . " ";

                        }else{

                            $name = $i['name'] ?? 'Item';

                            $qty = $i['qty'] ?? 1;

                            echo "$name x $qty<br>";
                        }
                    }

                }else{

                    echo $item;
                }

                echo "<br>";

                echo "• Payment: $payment";

                echo "</small><br>";

                ?>

                <small>
                    <?= $b['created_at'] ?? '' ?>
                </small>

            </div>

            <?php endforeach; ?>

        </div>

    </div>

</div>

<script>

const labels = <?= json_encode($labels) ?>;
const orderRevenue = <?= json_encode($orderRevenueData) ?>;     /* CHANGED: Passing order revenue array */
const bookingRevenue = <?= json_encode($bookingRevenueData) ?>; /* CHANGED: Passing booking revenue array */

const orders = <?= json_encode($orderCountData) ?>;
const bookings = <?= json_encode($bookingCountData) ?>;

new Chart(document.getElementById("revenueChart"), {

    type: 'line',

    data: {

        labels,

        datasets: [
            {
                label: 'Order Revenue',
                data: orderRevenue,
                borderColor: '#784f2b',    /* Styled line matching theme color */
                backgroundColor: 'rgba(120, 79, 43, 0.1)',
                borderWidth: 2,
                tension: 0.2
            },
            {
                label: 'Booking Revenue',
                data: bookingRevenue,
                borderColor: '#a06e42',    /* Styled line matching accent color */
                backgroundColor: 'rgba(160, 110, 66, 0.1)',
                borderWidth: 2,
                tension: 0.2
            }
        ]
    }

});

new Chart(document.getElementById("barChart"), {

    type: 'bar',

    data: {

        labels,

        datasets: [

            {
                label: 'Orders',
                data: orders
            },

            {
                label: 'Bookings',
                data: bookings
            }

        ]
    }

});

document.addEventListener('DOMContentLoaded', () => {
            
            // 1. DYNAMIC SIDEBAR DRAWER TOGGLE MODULE
            const toggleButton = document.getElementById('sidebarToggle');
            const sidebarElement = document.getElementById('sidebar');

            if (toggleButton && sidebarElement) {
                toggleButton.addEventListener('click', () => {
                    sidebarElement.classList.toggle('collapsed');
                });
            }

            // 2. SUBMENU ACCORDION TRIGGER ENGINE
            const submenuTriggers = document.querySelectorAll('.sidebar-submenu-trigger-btn');
            
            submenuTriggers.forEach(trigger => {
                trigger.addEventListener('click', (e) => {
                    const parentItem = trigger.closest('.sidebar-menu-dropdown-item');
                    
                    // Check if sidebar is collapsed - ignore accordion dropdown click states if closed
                    if (sidebarElement && sidebarElement.classList.contains('collapsed')) return;

                    e.preventDefault();
                    parentItem.classList.toggle('submenu-expanded');
                });
            });
        });

</script>

</body>
</html>