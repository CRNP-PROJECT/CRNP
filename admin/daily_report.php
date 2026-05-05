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

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
body{background:#f4f6f9;font-family:Arial;}
.card-box{border:none;border-radius:16px;box-shadow:0 4px 12px rgba(0,0,0,.08);background:#fff;}
.calendar{display:grid;grid-template-columns:repeat(7,1fr);gap:8px;}
.day-box{background:#fff;border-radius:12px;padding:10px;text-decoration:none;color:#000;min-height:95px;box-shadow:0 2px 6px rgba(0,0,0,.05);}
.day-box:hover{transform:scale(1.02);}
.day-number{font-weight:bold;}
.orders-only{background:#cfe2ff;}
.bookings-only{background:#fff3cd;}
.both{background:#d1e7dd;}
.active-day{border:2px solid #0d6efd;}
.muted{font-size:12px;color:#666;}
.kpi{text-align:center;padding:15px;}
</style>
</head>

<body>
    <nav class="navbar">
    

    <ul class="navbar-menu">
        <li><a href="admin_index.php" class="active">Admin Dashboard</a></li>
       
    </ul>
</nav>

<div class="container py-4">

<h3 class="mb-3">📊 POS Analytics Dashboard</h3>

<!-- VIEW BUTTONS -->
<div class="mb-2 text-center">

<a href="?view=daily&date=<?= $date ?>" class="btn btn-sm <?= $view=='daily'?'btn-primary':'btn-outline-primary' ?>">Daily</a>

<a href="?view=weekly&date=<?= $date ?>" class="btn btn-sm <?= $view=='weekly'?'btn-primary':'btn-outline-primary' ?>">Weekly</a>

<a href="?view=monthly&date=<?= $date ?>" class="btn btn-sm <?= $view=='monthly'?'btn-primary':'btn-outline-primary' ?>">Monthly</a>

</div>

<!-- MONTH NAVIGATION (ADDED) -->
<div class="text-center mb-3">

<a class="btn btn-sm btn-outline-dark" href="?view=monthly&date=<?= $prevMonth ?>">⬅ Prev Month</a>

<input type="month"
       value="<?= date('Y-m', strtotime($date)) ?>"
       onchange="location='?view=monthly&date='+this.value+'-01'">

<a class="btn btn-sm btn-outline-dark" href="?view=monthly&date=<?= $nextMonth ?>">Next Month ➡</a>

</div>

<div class="text-center mb-3">
<small class="text-muted">
📅 Daily • 📆 Weekly • 📊 Monthly
</small>
</div>

<!-- KPI -->
<div class="row g-3 mb-4">

<div class="col-md-3 card card-box kpi">
<h6>Orders</h6>
<h4><?= $dailyOrders ?></h4>
</div>

<div class="col-md-3 card card-box kpi">
<h6>Revenue</h6>
<h4><?= peso($dailyRevenue) ?></h4>
</div>

<div class="col-md-3 card card-box kpi">
<h6>Bookings</h6>
<h4><?= $dailyBookings ?></h4>
</div>

<div class="col-md-3 card card-box kpi">
<h6>Booking Revenue</h6>
<h4><?= peso($dailyBookingRevenue) ?></h4>
</div>

</div>



<!-- CALENDAR -->
<div class="card card-box p-3 mb-4">

<h5><?= date('F Y', strtotime($date)) ?></h5>

<div class="calendar">

<?php for($i=0;$i<7;$i++): ?>
<div class="text-center fw-bold"><?= ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][$i] ?></div>
<?php endfor; ?>

<?php for($i=0;$i<$startDay;$i++) echo "<div></div>"; ?>

<?php for($d=1;$d<=$daysInMonth;$d++):
$full = date('Y-m-d', strtotime("$year-$month-$d"));

$o = $calendar[$full]['orders'] ?? 0;
$b = $calendar[$full]['bookings'] ?? 0;
$or = $calendar[$full]['orderRevenue'] ?? 0;
$br = $calendar[$full]['bookingRevenue'] ?? 0;

$class = "";
if($o>0 && $b>0) $class="both";
elseif($o>0) $class="orders-only";
elseif($b>0) $class="bookings-only";
?>

<a href="?date=<?= $full ?>" class="day-box <?= $class ?> <?= $full==$date?'active-day':'' ?>">
<div class="day-number"><?= $d ?></div>
<div class="muted">Orders: <?= $o ?></div>
<div class="muted">Bookings: <?= $b ?></div>
<div class="muted"><?= peso($or + $br) ?></div>
</a>


<?php endfor; ?>

</div>
</div>

<div class="row g-3 mb-4">

<div class="col-md-6">
<div class="card card-box kpi">
<h6>📦 Monthly Order Revenue</h6>
<h4><?= peso($monthlyOrderRevenue) ?></h4>
</div>
</div>

<div class="col-md-6">
<div class="card card-box kpi">
<h6>📅 Monthly Booking Revenue</h6>
<h4><?= peso($monthlyBookingRevenue) ?></h4>
</div>
</div>

</div>

<!-- CHARTS -->
<div class="row g-3 mb-4">

<div class="col-md-6">
<div class="card card-box p-3">
<h5>📈 Revenue Trend</h5>
<canvas id="revenueChart"></canvas>
</div>
</div>

<div class="col-md-6">
<div class="card card-box p-3">
<h5>📊 Orders vs Bookings</h5>
<canvas id="barChart"></canvas>
</div>
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
                borderColor: 'blue',
                borderWidth: 2,
                tension: 0.3
            },
            {
                label: 'Bookings Revenue',
                data: bookingRevenue,
                borderColor: 'red',
                borderWidth: 2,
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
            { label:'Orders', data: orders },
            { label:'Bookings', data: bookings }
        ]
    }
});
</script>
<!-- DETAILS -->
<div class="row">

<div class="col-md-6">
<div class="card card-box p-3">
<h5>Orders</h5>
<?php foreach($selectedOrders as $o): ?>
<div class="border-bottom py-2">
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

/* ================= PAYMENT METHOD ================= */
$payment = $o['payment_method'] ?? 'N/A';
echo "<small><b>Payment Method:</b> $payment</small><br>";

?>
<small><?= $o['created_at'] ?? '' ?></small>
</div>
<?php endforeach; ?>
</div>
</div>

<div class="col-md-6">
<div class="card card-box p-3">
<h5>Bookings (Accepted Only)</h5>
<?php foreach($selectedBookings as $b): ?>
<div class="border-bottom py-2">
<b><?= peso($b['booking_total'] ?? 0) ?></b><br>
<?php
$item = $b['items'] ?? 'No Item';
$payment = $b['payment_method'] ?? 'N/A';

echo "<small><b>Booking Details:</b><br>";

// ITEMS
echo "• Items: ";

if (is_array($item)) {
    foreach ($item as $i) {
        // if item is string
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

// PAYMENT
echo "• Payment: $payment";

echo "</small><br>";
?>
<small><?= $b['created_at'] ?? '' ?></small>
</div>
<?php endforeach; ?>

</body>
</html>

