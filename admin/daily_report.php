<?php
session_start();

include(__DIR__ . "/../config.php");
require_once(__DIR__ . "/../firebaseRDB.php");

$report = include(__DIR__ . "/daily_report_process.php");

if(!is_array($report)){
    die("Process file did not return data properly.");
}
/* ================= MAP VARIABLES ================= */
$date = $report['date'];
$view = $_GET['view'] ?? 'daily';

$dailyOrders = $report['dailyOrders'];
$dailyBookings = $report['dailyBookings'];

$dailyRevenue = $report['dailyRevenue'];
$dailyBookingRevenue = $report['dailyBookingRevenue'];

$calendar = $report['calendar'];

$labels = $report['labels'];
$orderRevenueData = $report['orderRevenueData'];
$bookingRevenueData = $report['bookingRevenueData'];
$orderCountData = $report['orderCountData'];
$bookingCountData = $report['bookingCountData'];

$selectedOrders = $report['selectedOrders'];
$selectedBookings = $report['selectedBookings'];

$monthlyOrderRevenue = $report['monthlyOrderRevenue'];
$monthlyBookingRevenue = $report['monthlyBookingRevenue'];

$year = date('Y', strtotime($date));
$month = date('m', strtotime($date));

$startDay = date('w', strtotime("$year-$month-01"));
$daysInMonth = date('t', strtotime("$year-$month-01"));

$prevMonth = date('Y-m-d', strtotime($date . ' -1 month'));
$nextMonth = date('Y-m-d', strtotime($date . ' +1 month'));
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>POS Analytics Dashboard</title>
<link rel="stylesheet" href="../style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="daily_report_body">

<!-- NAVBAR -->
<nav class="navbar">
    <div class="navbar-brand-container">
        <img src="../img/logo.png" class="logo">
    </div>

    <ul class="navbar-menu">
        <li><a href="admin_index.php">Admin Dashboard</a></li>
        <li class="navbar-dropdown">
            <a href="#">Products ▼</a>
            <div class="navbar-dropdown-content">
                <a href="add_product.php">Add Product</a>
                <a href="product_list.php">Product List</a>
            </div>
        </li>
        <li class="navbar-dropdown">
            <a href="#">Bookings ▼</a>
            <div class="navbar-dropdown-content">
                <a href="booking_add.php">Booking Items</a>
                <a href="booking_reserve.php">Booking List</a>
                <a href="booking_add.php">Booking</a>
            </div>
        </li>

        <li><a href="daily_report.php" class="active">Daily report</a></li>
        <li><a href="admin_log.php">Logout</a></li>
    </ul>
</nav>

<div class="daily_report_container">

    <div class="daily_report_title_section">
        <h1 class="daily_report_title">Daily Report Dashboard</h1>
        <p class="daily_report_subtitle">
            Monitor revenues, orders, bookings, and analytics
        </p>
    </div>

    <!-- KPI CARDS -->
    <div class="daily_report_kpi_grid">

        <div class="daily_report_kpi_card">
            <h6>Daily Revenue</h6>
            <h4><?= peso($dailyRevenue) ?></h4>
        </div>

        <div class="daily_report_kpi_card">
            <h6>Bookings</h6>
            <h4><?= $dailyBookings ?></h4>
        </div>

        <div class="daily_report_kpi_card">
            <h6>Booking Revenue</h6>
            <h4><?= peso($dailyBookingRevenue) ?></h4>
        </div>

    </div>

    <!-- CALENDAR -->
    <div class="daily_report_card">

        <div class="daily_report_calendar_header">
            <h5><?= date('F Y', strtotime($date)) ?></h5>
        </div>

        <div class="daily_report_calendar">

            <?php for($i=0;$i<7;$i++): ?>
                <div class="daily_report_calendar_dayname">
                    <?= ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][$i] ?>
                </div>
            <?php endfor; ?>

            <?php for($i=0;$i<$startDay;$i++) echo "<div></div>"; ?>

            <?php for($d=1;$d<=$daysInMonth;$d++):
                $full = date('Y-m-d', strtotime("$year-$month-$d"));

                $o = $calendar[$full]['orders'] ?? 0;
                $b = $calendar[$full]['bookings'] ?? 0;
                $or = $calendar[$full]['orderRevenue'] ?? 0;
                $br = $calendar[$full]['bookingRevenue'] ?? 0;

                $class = "";
                if($o>0 && $b>0) $class="daily_report_both";
                elseif($o>0) $class="daily_report_orders_only";
                elseif($b>0) $class="daily_report_bookings_only";
            ?>

            <a href="?date=<?= $full ?>"
               class="daily_report_day_box <?= $class ?> <?= $full==$date?'daily_report_active_day':'' ?>">

                <div class="daily_report_day_number"><?= $d ?></div>

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

    <!-- MONTHLY -->
    <div class="daily_report_two_column">

        <div class="daily_report_kpi_card">
            <h6>Monthly Order Revenue</h6>
            <h4><?= peso($monthlyOrderRevenue) ?></h4>
        </div>

        <div class="daily_report_kpi_card">
            <h6>Monthly Booking Revenue</h6>
            <h4><?= peso($monthlyBookingRevenue) ?></h4>
        </div>

    </div>

    <!-- CHARTS -->
    <div class="daily_report_two_column">

        <div class="daily_report_card">
            <h5 class="daily_report_card_title">Revenue Trend</h5>
            <canvas id="revenueChart"></canvas>
        </div>

        <div class="daily_report_card">
            <h5 class="daily_report_card_title">Orders vs Bookings</h5>
            <canvas id="barChart"></canvas>
        </div>

    </div>

    <!-- DETAILS -->
    <div class="daily_report_two_column">

        <!-- ORDERS -->
        <div class="daily_report_card">

            <h5 class="daily_report_card_title">Orders</h5>

            <?php foreach($selectedOrders as $o): ?>

            <div class="daily_report_detail_item">

                <b><?= peso($o['total'] ?? 0) ?></b><br>

                <?php
                $products = $o['products'] ?? [];

                if(is_string($products)){
                    $products = json_decode($products, true);
                }

                if(is_array($products) && !empty($products)){
                    echo "<small><b>Items Ordered:</b><br>";

                    foreach($products as $p){
                        $name = $p['name'] ?? $p['product_name'] ?? 'Item';
                        $qty  = $p['qty'] ?? $p['quantity'] ?? 1;

                        echo "• $name x $qty<br>";
                    }

                    echo "</small><br>";
                }

                $payment = $o['payment_method'] ?? 'N/A';
                echo "<small><b>Payment Method:</b> $payment</small><br>";
                ?>

                <small><?= $o['created_at'] ?? '' ?></small>

            </div>

            <?php endforeach; ?>

        </div>

        <!-- BOOKINGS -->
        <div class="daily_report_card">

            <h5 class="daily_report_card_title">
                Bookings (Accepted Only)
            </h5>

            <?php foreach($selectedBookings as $b): ?>

            <div class="daily_report_detail_item">

                <b><?= peso($b['booking_total'] ?? 0) ?></b><br>

                <?php
                $item = $b['items'] ?? 'No Item';
                $payment = $b['payment_method'] ?? 'N/A';

                echo "<small><b>Booking Details:</b><br>";

                echo "• Items: ";

                if (is_array($item)) {
                    foreach ($item as $i) {

                        if (is_string($i)) {
                            echo $i . " ";
                        } else {
                            $name = $i['name'] ?? 'Item';
                            $qty  = $i['qty'] ?? 1;

                            echo "$name x $qty<br>";
                        }
                    }
                } else {
                    echo $item;
                }

                echo "<br>";
                echo "• Payment: $payment";

                echo "</small><br>";
                ?>

                <small><?= $b['created_at'] ?? '' ?></small>

            </div>

            <?php endforeach; ?>

        </div>

    </div>

</div>

<script>
const labels = <?= json_encode($labels) ?>;
const orderRevenue = <?= json_encode($orderRevenueData) ?>;
const bookingRevenue = <?= json_encode($bookingRevenueData) ?>;
const orders = <?= json_encode($orderCountData) ?>;
const bookings = <?= json_encode($bookingCountData) ?>;

new Chart(document.getElementById("revenueChart"), {
    type: 'line',
    data: {
        labels: labels,
        datasets: [
            {
                label: 'Orders Revenue',
                data: orderRevenue,
                borderColor: '#4f46e5',
                backgroundColor: 'transparent',
                borderWidth: 3,
                tension: 0.3
            },
            {
                label: 'Bookings Revenue',
                data: bookingRevenue,
                borderColor: '#06b6d4',
                backgroundColor: 'transparent',
                borderWidth: 3,
                tension: 0.3
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
                label:'Orders',
                data: orders,
                backgroundColor: '#4f46e5'
            },
            {
                label:'Bookings',
                data: bookings,
                backgroundColor: '#06b6d4'
            }
        ]
    }
});
</script>

</body>
</html>